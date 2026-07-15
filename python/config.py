# Configuration constants and request-authorization helpers for the ML gateway.

import os

DEFAULT_SPAM_KEYWORDS = (
    "crypto,buy now,click here,free money,viagra,loan approved,"
    "earn cash,work from home,winner,claim your prize,act now,risk free"
)
DEFAULT_CATEGORY = "General Chat"
DEFAULT_RECOMMENDATION_LIMIT = 10
SIMILARITY_FLOOR = 0.05
CATALOG_TTL_SECONDS = 60
TRENDING_WINDOW_DAYS = 14
RECENT_ACTIVITY_LIMIT = 10
GROUP_SUGGESTION_LIMIT = 5
GROUP_ACTIVITY_WEIGHT = 2
DB_CONNECT_TIMEOUT_SECONDS = 3
# Max words allowed between a multi-word spam phrase's terms to still count as a match.
SPAM_PHRASE_MATCH_SLACK = 3


def _expected_token() -> str | None:
    # Shared secret the caller's Authorization header must match.
    return os.environ.get("GATEWAY_EXPECTED_TOKEN") or os.environ.get("GATEWAY_TOKEN")


def _token_prefix() -> str:
    return os.environ.get("GATEWAY_TOKEN_PREFIX", "Bearer ")


def _is_authorized(req) -> bool:
    expected = _expected_token()
    if not expected:
        return False
    prefix = _token_prefix()
    header = req.headers.get("Authorization", "")
    if not header.startswith(prefix):
        return False
    return header[len(prefix):] == expected


def _default_category() -> str:
    return os.environ.get("DEFAULT_CATEGORY", DEFAULT_CATEGORY)


def _recommendation_limit() -> int:
    try:
        return max(1, int(os.environ.get("RECOMMENDATION_LIMIT", DEFAULT_RECOMMENDATION_LIMIT)))
    except (TypeError, ValueError):
        return DEFAULT_RECOMMENDATION_LIMIT
