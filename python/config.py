# Configuration constants and request authorization helpers for the ML gateway.

import os

# Category assigned when nothing beats SIMILARITY_FLOOR against the catalog.
DEFAULT_CATEGORY = "General Chat"
# Minimum cosine similarity a text needs against the best-matching catalog
# category before that category is trusted. Below this, the match is
# treated as noise and DEFAULT_CATEGORY is used instead.
SIMILARITY_FLOOR = 0.05
# How long a built category catalog is reused before catalog.py rebuilds it
# from the database again.
CATALOG_TTL_SECONDS = 60
# How many days back "recent activity" (posts/replies) counts toward a
# group's trending score.
TRENDING_WINDOW_DAYS = 14
RECENT_ACTIVITY_LIMIT = 10
TRENDING_GROUP_LIMIT = 5
# How much more a post/reply counts than a plain membership when ranking
# trending groups (see db.py's trending query).
GROUP_ACTIVITY_WEIGHT = 2
# Max seconds to wait for a database connection before giving up and
# falling back to "database unavailable" behavior.
DB_CONNECT_TIMEOUT_SECONDS = 3


def _expected_token() -> str | None:
    # Reads the token this gateway expects callers to present, checking
    # GATEWAY_EXPECTED_TOKEN first and falling back to GATEWAY_TOKEN.
    # Returns None if neither is set, which _is_authorized treats as
    # "nothing configured, reject every request."
    return os.environ.get("GATEWAY_EXPECTED_TOKEN") or os.environ.get("GATEWAY_TOKEN")


def _token_prefix() -> str:
    # Prefix stripped off the Authorization header before comparing against
    # the expected token, e.g. "Bearer " in "Bearer <token>".
    return os.environ.get("GATEWAY_TOKEN_PREFIX", "Bearer ")


def _is_authorized(req) -> bool:
    # Validates the incoming request's Authorization header against the
    # configured expected token. Used by every endpoint in app.py to
    # reject unauthenticated calls with a 401.
    expected = _expected_token()
    if not expected:
        return False
    prefix = _token_prefix()
    header = req.headers.get("Authorization", "")
    if not header.startswith(prefix):
        return False
    return header[len(prefix):] == expected


def _default_category() -> str:
    # Env override for DEFAULT_CATEGORY, falling back to the constant above.
    return os.environ.get("DEFAULT_CATEGORY", DEFAULT_CATEGORY)