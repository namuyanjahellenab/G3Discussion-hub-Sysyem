# Configuration constants and request-authorization helpers for the ML gateway.

import os

DEFAULT_CATEGORY = "General Chat"
SIMILARITY_FLOOR = 0.05
CATALOG_TTL_SECONDS = 60
TRENDING_WINDOW_DAYS = 14
RECENT_ACTIVITY_LIMIT = 10
TRENDING_GROUP_LIMIT = 5
GROUP_ACTIVITY_WEIGHT = 2
DB_CONNECT_TIMEOUT_SECONDS = 3


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


