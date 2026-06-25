from flask import Flask, request, jsonify

app = Flask(__name__)

# Verify incoming authorization headers from the system gateway layer
def verify_gateway_auth(auth_header):
    if not auth_header or not auth_header.startswith("Bearer "):
        return False
    token_parts = auth_header.split(" ")
    return len(token_parts) > 1

@app.route('/classify', methods=['POST'])
def classify_message():
    """
    Real-Time Instant Message Chat Classifier.
    """
    auth_header = request.headers.get("Authorization")
    if not verify_gateway_auth(auth_header):
        return jsonify({"error": "Unauthorized internal service token context."}), 401

    data = request.get_json()
    if not data or 'MessageText' not in data:
        return jsonify({"error": "Missing 'MessageText' string parameter in payload."}), 400
        
    raw_text = data.get('MessageText', '').strip()
    if not raw_text:
        return jsonify({"error": "The 'MessageText' value string cannot be empty."}), 422
        
    message_id = data.get("MessageID", 1)
    
    # Check for spam keywords or flooding strings
    is_filtered = False
    spam_keywords = ["buy", "crypto", "win cash", "advertisement", "free link"]
    if any(keyword in raw_text.lower() for keyword in spam_keywords):
        is_filtered = True

    classification_payload = {
        "MessageID": int(message_id),
        "PredictedCategory": "General Chat" if not is_filtered else "Spam/Filtered Content",
        "ConfidenceScore": 0.95,
        "IsFiltered": is_filtered
    }
    
    return jsonify(classification_payload), 200

@app.route('/recommend', methods=['POST'])
def recommend_chat_topics():
    """
    Personalized Chat Room Recommender Engine.
    """
    auth_header = request.headers.get("Authorization")
    if not verify_gateway_auth(auth_header):
        return jsonify({"error": "Unauthorized internal service token context."}), 401

    data = request.get_json()
    if not data or 'UserID' not in data:
        return jsonify({"error": "Missing 'UserID' parameter in recommendation engine profile."}), 400
        
    user_id = data.get('UserID')
    
    recommendation_payload = {
        "UserID": user_id,
        "Recommendations": [
            {
                "TopicID": 5,
                "Category": "Laravel Realtime WebSockets",
                "RelevanceScore": 0.98
            },
            {
                "TopicID": 12,
                "Category": "Java Network Sockets",
                "RelevanceScore": 0.89
            }
        ]
    }
    
    return jsonify(recommendation_payload), 200

@app.errorhandler(404)
def route_not_found(error):
    return jsonify({"error": "Target Chat ML Gateway routing endpoint not found."}), 404

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
