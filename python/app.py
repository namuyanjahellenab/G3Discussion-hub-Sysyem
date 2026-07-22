# ML gateway for the Discussion Hub Laravel app.
# Endpoints: /classify, /recommend-topics, /trending-groups, /export-topic-pdf, /topic-share-links
# Classification/ranking uses TF-IDF + cosine similarity against a catalog rebuilt from live Topic data.
# Helpers are imported by name so tests can keep monkeypatching them directly.

import logging
import os

from dotenv import load_dotenv
from flask import Flask, Response, jsonify, request
from flask.typing import ResponseReturnValue

from catalog import _clamp_score, _classify_category, _rank_texts
from config import GROUP_ACTIVITY_WEIGHT, _is_authorized
from db import (
    _fetch_topic_export_data,
    _fetch_topic_share_data,
    _fetch_trending_groups,
    _fetch_user_recent_text,
)
from pdf import _build_topic_pdf, _slugify
from share import _build_share_links
from spam import _classify_content

load_dotenv()

app = Flask(__name__)


@app.route("/classify", methods=["POST"])
def classify() -> ResponseReturnValue:
    # Predict a category for a single piece of text. _classify_content covers
    # both the spam short-circuit and whether the message is educational/
    # on-topic for an academic discussion board.
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    message_text = payload.get("MessageText") or ""
    message_id = payload.get("MessageID")
    context = payload.get("Context")

    verdict = _classify_content(message_text, context=context)

    if verdict["is_spam"]:
        return jsonify({
            "MessageID": message_id,
            "PredictedCategory": "Spam/Filtered Content",
            "ConfidenceScore": 1.0,
            "IsFiltered": True,
            "IsEducational": verdict["is_educational"],
        })

    category, score = _classify_category(message_text)
    return jsonify({
        "MessageID": message_id,
        "PredictedCategory": category,
        "ConfidenceScore": score,
        "IsFiltered": False,
        "IsEducational": verdict["is_educational"],
    })


@app.route("/recommend-topics", methods=["POST"])
def recommend_topics() -> ResponseReturnValue:
    # Rank specific candidate topics for a user by relevance, scoring each
    # topic's own title text against the user's interests/activity, so
    # topics sharing a category no longer collapse onto one identical,
    # duplicated score.
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    user_id = payload.get("UserID")
    interests = payload.get("Interests") or []
    recent_messages = payload.get("RecentMessages") or []
    topics = payload.get("Topics") or []

    db_recent_text = _fetch_user_recent_text(user_id)
    query_text = " ".join(
        str(part) for part in [*interests, *recent_messages, *(db_recent_text or [])]
    ).strip()

    if not query_text or not topics:
        topic_scores = [{"TopicID": topic.get("TopicID"), "RelevanceScore": 0.0} for topic in topics]
        return jsonify({"UserID": user_id, "TopicScores": topic_scores})

    # The title is repeated 3x rather than dropping Category entirely.
    # Most topics in a group share the same category, so weighting it
    # equally with a short title let that one repeated word dominate the
    # score - collapsing unrelated topics in the same category onto nearly
    # identical results. Category still gives every topic a baseline score
    # (so an unrelated title doesn't score a flat 0), but a title that
    # actually matches the query now outweighs it, as intended.
    texts = [
        ((topic.get("Title") or "") + " ") * 3 + (topic.get("Category") or "")
        for topic in topics
    ]
    scores = _rank_texts(query_text, texts)
    topic_scores = [
        {"TopicID": topic.get("TopicID"), "RelevanceScore": round(score, 4)}
        for topic, score in zip(topics, scores)
    ]
    return jsonify({"UserID": user_id, "TopicScores": topic_scores})


@app.route("/trending-groups", methods=["POST"])
def trending_groups() -> ResponseReturnValue:
    # Every group ranked by member count + weighted recent activity, joined
    # or not - an objective "what's popular right now" signal, not a
    # personalized "you haven't joined this" suggestion.
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    rows = _fetch_trending_groups()
    if not rows:
        return jsonify({"TrendingGroups": []})

    max_score = max(
        (row["MemberCount"] or 0) + GROUP_ACTIVITY_WEIGHT * (row["RecentActivity"] or 0) for row in rows
    ) or 1
    trending = [
        {
            "GroupID": row["GroupID"],
            "GroupName": row["GroupName"],
            "InteractionScore": round(
                _clamp_score(((row["MemberCount"] or 0) + GROUP_ACTIVITY_WEIGHT * (row["RecentActivity"] or 0)) / max_score), 4
            ),
        }
        for row in rows
    ]
    return jsonify({"TrendingGroups": trending})


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
