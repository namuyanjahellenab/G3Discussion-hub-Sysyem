# ML gateway for the Discussion Hub Laravel app.
# Endpoints: /classify, /recommend, /recommend-groups, /export-topic-pdf, /topic-share-links
# Classification/ranking uses TF-IDF + cosine similarity against a catalog rebuilt from live Topic data.
# Helpers are imported by name so tests can keep monkeypatching them directly.

import logging
import os

from dotenv import load_dotenv
from flask import Flask, Response, jsonify, request
from flask.typing import ResponseReturnValue

from catalog import _build_catalog, _clamp_score, _classify_category, _rank_against_catalog
from config import GROUP_ACTIVITY_WEIGHT, _is_authorized, _recommendation_limit
from db import (
    _fetch_group_suggestions,
    _fetch_topic_export_data,
    _fetch_topic_share_data,
    _fetch_trending_categories,
    _fetch_user_recent_text,
    _run_query,
)
from pdf import _build_topic_pdf, _slugify
from share import _build_share_links
from spam import _is_spam

load_dotenv()

app = Flask(__name__)


@app.route("/classify", methods=["POST"])
def classify() -> ResponseReturnValue:
    # Predict a category for a single piece of text, with a spam short-circuit.
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    message_text = payload.get("MessageText") or ""
    message_id = payload.get("MessageID")

    if _is_spam(message_text):
        return jsonify({
            "MessageID": message_id,
            "PredictedCategory": "Spam/Filtered Content",
            "ConfidenceScore": 1.0,
            "IsFiltered": True,
        })

    category, score = _classify_category(message_text)
    return jsonify({
        "MessageID": message_id,
        "PredictedCategory": category,
        "ConfidenceScore": score,
        "IsFiltered": False,
    })


@app.route("/recommend", methods=["POST"])
def recommend() -> ResponseReturnValue:
    # Rank categories for a user: personalized (real activity) or trending (cold-start).
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    user_id = payload.get("UserID")
    interests = payload.get("Interests") or []
    recent_messages = payload.get("RecentMessages") or []
    group_ids = payload.get("GroupIDs") or []
    limit = _recommendation_limit()

    # None = DB unreachable, fall back to Interests/RecentMessages. [] = real cold-start user.
    db_recent_text = _fetch_user_recent_text(user_id)
    cold_start = db_recent_text is not None and len(db_recent_text) == 0

    if cold_start:
        trending = _fetch_trending_categories(group_ids)
        if trending:
            max_engagement = max(row["Engagement"] for row in trending) or 1
            recommendations = [
                {
                    "Category": row["Category"],
                    "RelevanceScore": round(_clamp_score(row["Engagement"] / max_engagement), 4),
                }
                for row in trending[:limit]
            ]
            return jsonify({"UserID": user_id, "Recommendations": recommendations, "Mode": "trending"})

    query_text = " ".join(
        [str(part) for part in [*interests, *recent_messages, *(db_recent_text or [])]]
    ).strip()

    if not query_text:
        catalog = _build_catalog()
        recommendations = [
            {"Category": item["Category"], "RelevanceScore": 0.0}
            for item in catalog[:limit]
        ]
        return jsonify({"UserID": user_id, "Recommendations": recommendations})

    catalog = _build_catalog()
    ranked = sorted(_rank_against_catalog(query_text, catalog), key=lambda pair: pair[1], reverse=True)
    recommendations = [
        {"Category": item["Category"], "RelevanceScore": round(_clamp_score(score), 4)}
        for item, score in ranked[:limit]
    ]

    return jsonify({"UserID": user_id, "Recommendations": recommendations, "Mode": "personalized"})


@app.route("/recommend-groups", methods=["POST"])
def recommend_groups() -> ResponseReturnValue:
    # Suggest groups the user hasn't joined, ranked by member count + activity.
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    user_id = payload.get("UserID")
    group_ids = payload.get("GroupIDs") or []

    rows = _fetch_group_suggestions(user_id, group_ids)
    if not rows:
        return jsonify({"UserID": user_id, "SuggestedGroups": []})

    max_score = max(
        (row["MemberCount"] or 0) + GROUP_ACTIVITY_WEIGHT * (row["RecentActivity"] or 0) for row in rows
    ) or 1
    suggestions = [
        {
            "GroupID": row["GroupID"],
            "GroupName": row["GroupName"],
            "InteractionScore": round(
                _clamp_score(((row["MemberCount"] or 0) + GROUP_ACTIVITY_WEIGHT * (row["RecentActivity"] or 0)) / max_score), 4
            ),
        }
        for row in rows
    ]
    return jsonify({"UserID": user_id, "SuggestedGroups": suggestions})


@app.route("/export-topic-pdf", methods=["POST"])
def export_topic_pdf() -> ResponseReturnValue:
    # Render a topic + its posts/replies to a downloadable PDF.
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    topic_id = payload.get("TopicID")
    if not topic_id:
        return jsonify({"error": "TopicID is required"}), 400

    data = _fetch_topic_export_data(topic_id)
    if data is None:
        return jsonify({"error": "database unavailable"}), 503
    if data["topic"] is None:
        return jsonify({"error": "topic not found"}), 404

    pdf_bytes = _build_topic_pdf(data["topic"], data["posts"])
    if pdf_bytes is None:
        app.logger.warning("PDF generation failed for TopicID=%s", topic_id)
        return jsonify({"error": "pdf generation failed"}), 500

    filename = _slugify(data["topic"]["Title"] or "discussion") + "-discussion.pdf"
    return Response(
        pdf_bytes,
        mimetype="application/pdf",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )


@app.route("/topic-share-links", methods=["POST"])
def topic_share_links() -> ResponseReturnValue:
    # Build a share URL, a pre-filled text snippet, and links for WhatsApp/Twitter/Facebook/Email.
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    topic_id = payload.get("TopicID")
    base_url = (payload.get("BaseUrl") or "").rstrip("/")
    if not topic_id or not base_url:
        return jsonify({"error": "TopicID and BaseUrl are required"}), 400

    data = _fetch_topic_share_data(topic_id)
    if data is None:
        return jsonify({"error": "database unavailable"}), 503
    if data["title"] is None:
        return jsonify({"error": "topic not found"}), 404

    return jsonify(_build_share_links(topic_id, data["title"], data["reply_count"], base_url))


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", 5000)))
