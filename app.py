import os

from flask import Flask, jsonify, request
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

app = Flask(__name__)

DEFAULT_SPAM_KEYWORDS = (
    "crypto,buy now,click here,free money,viagra,loan approved,"
    "earn cash,work from home,winner,claim your prize,act now,risk free"
)
DEFAULT_CATEGORY = "General Chat"
DEFAULT_RECOMMENDATION_LIMIT = 5
SIMILARITY_FLOOR = 0.05

# Seed catalog the classifier/recommender score requests against. Real topic
# categories live in the Laravel DB (Topic.Category); this catalog only needs
# to cover the categories the gateway is expected to recognize.
CATEGORY_CATALOG = [
    {"Category": "General Chat", "Text": "hello hi general chat greeting introduction casual conversation"},
    {"Category": "Laravel Realtime WebSockets", "Text": "laravel realtime websockets broadcasting echo pusher socket chat notifications live updates"},
    {"Category": "Java Network Sockets", "Text": "java network sockets tcp udp streams io programming server client networking"},
    {"Category": "Database Design", "Text": "database design schema normalization sql tables relationships mysql indexing"},
    {"Category": "Machine Learning", "Text": "machine learning scikit-learn tensorflow model training classification regression dataset"},
    {"Category": "Web Development", "Text": "web development html css javascript frontend backend framework responsive"},
    {"Category": "Mobile Development", "Text": "mobile development android ios flutter react native app"},
    {"Category": "Group Projects & Collaboration", "Text": "group project teamwork collaboration assignment deadline meeting schedule"},
]


def _expected_token():
    return os.environ.get("GATEWAY_EXPECTED_TOKEN") or os.environ.get("GATEWAY_TOKEN")


def _token_prefix():
    return os.environ.get("GATEWAY_TOKEN_PREFIX", "Bearer ")


def _is_authorized(req):
    expected = _expected_token()
    if not expected:
        return False
    prefix = _token_prefix()
    header = req.headers.get("Authorization", "")
    if not header.startswith(prefix):
        return False
    return header[len(prefix):] == expected


def _spam_keywords():
    raw = os.environ.get("SPAM_KEYWORDS", DEFAULT_SPAM_KEYWORDS)
    return [keyword.strip().lower() for keyword in raw.split(",") if keyword.strip()]


def _default_category():
    return os.environ.get("DEFAULT_CATEGORY", DEFAULT_CATEGORY)


def _recommendation_limit():
    try:
        return max(1, int(os.environ.get("RECOMMENDATION_LIMIT", DEFAULT_RECOMMENDATION_LIMIT)))
    except (TypeError, ValueError):
        return DEFAULT_RECOMMENDATION_LIMIT


def _is_spam(text):
    lowered = text.lower()
    return any(keyword in lowered for keyword in _spam_keywords())


def _rank_against_catalog(query_text):
    corpus = [item["Text"] for item in CATEGORY_CATALOG] + [query_text]
    vectorizer = TfidfVectorizer(stop_words="english")
    matrix = vectorizer.fit_transform(corpus)
    similarities = cosine_similarity(matrix[-1], matrix[:-1])[0]
    return list(zip(CATEGORY_CATALOG, similarities))


def _classify_category(text):
    ranked = sorted(_rank_against_catalog(text), key=lambda pair: pair[1], reverse=True)
    best_item, best_score = ranked[0]
    if best_score <= SIMILARITY_FLOOR:
        return _default_category(), 0.0
    return best_item["Category"], round(float(best_score), 4)


@app.route("/classify", methods=["POST"])
def classify():
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
def recommend():
    if not _is_authorized(request):
        return jsonify({"error": "unauthorized"}), 401

    payload = request.get_json(silent=True) or {}
    user_id = payload.get("UserID")
    interests = payload.get("Interests") or []
    recent_messages = payload.get("RecentMessages") or []
    limit = _recommendation_limit()

    query_text = " ".join([str(part) for part in [*interests, *recent_messages]]).strip()

    if not query_text:
        recommendations = [
            {"Category": item["Category"], "RelevanceScore": 0.0}
            for item in CATEGORY_CATALOG[:limit]
        ]
        return jsonify({"UserID": user_id, "Recommendations": recommendations})

    ranked = sorted(_rank_against_catalog(query_text), key=lambda pair: pair[1], reverse=True)
    recommendations = [
        {"Category": item["Category"], "RelevanceScore": round(float(score), 4)}
        for item, score in ranked[:limit]
    ]

    return jsonify({"UserID": user_id, "Recommendations": recommendations})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", 5000)))
