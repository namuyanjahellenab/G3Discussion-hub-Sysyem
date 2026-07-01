# Discussion Hub Gateway

This service provides the  ML and integration endpoints for the Discussion Hub project.

## Endpoints
- POST /classify
- POST /recommend

## Environment variables
- GATEWAY_EXPECTED_TOKEN
- GATEWAY_TOKEN_PREFIX
- SPAM_KEYWORDS
- DEFAULT_CATEGORY
- RECOMMENDATION_LIMIT

## Notes
- The classifier uses a lightweight TF-IDF + cosine similarity pipeline with spam keyword detection.
- The recommender uses recent user messages and interests to rank topic suggestions.
