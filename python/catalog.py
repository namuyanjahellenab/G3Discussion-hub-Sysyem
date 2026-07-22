# Builds a TF-IDF category catalog and classifies/ranks text against it.
# The catalog starts as a static seed list, then gets enriched with live
# Topic data from the database and cached for a while.

import time
from typing import Any

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from config import CATALOG_TTL_SECONDS, SIMILARITY_FLOOR, _default_category
from db import _fetch_live_categories

# Seed catalog. Bootstraps classification on a fresh install and acts as a
# fallback whenever the database is unreachable. Categories match the app's
# seeded groups (database/seeders/GroupSeeder.php).
CATEGORY_CATALOG: list[dict[str, str]] = [
    {"Category": "General Chat", "Text": "hello hi general chat greeting introduction casual conversation"},
    {"Category": "Algorithms", "Text": "algorithms data structures sorting searching complexity recursion dynamic programming big o problem solving"},
    {"Category": "Databases", "Text": "database design schema normalization sql tables relationships mysql indexing queries joins transactions"},
    {"Category": "Networks", "Text": "networks sockets tcp udp protocols routing server client networking data transmission packets"},
    {"Category": "Software Engineering", "Text": "software engineering design patterns architecture agile testing version control requirements web development frameworks"},
    {"Category": "Group Projects & Collaboration", "Text": "group project teamwork collaboration assignment deadline meeting schedule"},
]

_catalog_cache: dict[str, Any] = {"built_at": 0.0, "catalog": None}


def _clamp_score(score: float) -> float:
    # Keeps a similarity/ratio score within [0, 1], since floating-point
    # rounding can occasionally push a perfect match slightly past 1.0.
    return max(0.0, min(float(score), 1.0))


def _build_catalog() -> list[dict[str, str]]:
    # Enriching the catalog means querying the database, which would be
    # wasteful on every single /classify call. Caching the result for
    # CATALOG_TTL_SECONDS lets many requests reuse it before it's rebuilt.
    now = time.time()
    cached = _catalog_cache.get("catalog")
    if cached is not None and (now - _catalog_cache.get("built_at", 0)) < CATALOG_TTL_SECONDS:
        return cached

    merged = {item["Category"]: item["Text"] for item in CATEGORY_CATALOG}
    for row in (_fetch_live_categories() or []):
        category = (row.get("Category") or "").strip()
        text = row.get("Text") or ""
        if not category:
            continue
        if category in merged:
            merged[category] = f"{merged[category]} {text}".strip()
        else:
            merged[category] = text

    catalog = [{"Category": category, "Text": text} for category, text in merged.items()]
    _catalog_cache["catalog"] = catalog
    _catalog_cache["built_at"] = now
    return catalog


def _rank_against_catalog(query_text: str, catalog: list[dict[str, str]]) -> list[tuple[dict[str, str], float]]:
    # Scores query_text against every catalog entry by cosine similarity.
    # Returns (entry, score) pairs in the catalog's original order; the
    # caller decides how to pick the best match.
    corpus = [item["Text"] for item in catalog] + [query_text]
    vectorizer = TfidfVectorizer(stop_words="english")
    matrix = vectorizer.fit_transform(corpus)
    similarities = cosine_similarity(matrix[-1], matrix[:-1])[0]
    return list(zip(catalog, similarities))


def _rank_texts(query_text: str, texts: list[str]) -> list[float]:
    # Same idea as _rank_against_catalog, but for ranking arbitrary texts
    # (e.g. topic titles) instead of the fixed category catalog. Returns
    # one score per text, in the same order given.
    corpus = texts + [query_text]
    vectorizer = TfidfVectorizer(stop_words="english")
    matrix = vectorizer.fit_transform(corpus)
    similarities = cosine_similarity(matrix[-1], matrix[:-1])[0]
    return [_clamp_score(score) for score in similarities]


def _classify_category(text: str) -> tuple[str, float]:
    # Returns the best-matching category for text, or DEFAULT_CATEGORY at
    # score 0.0 if even the closest match is too weak to trust (below
    # SIMILARITY_FLOOR).
    catalog = _build_catalog()
    ranked = sorted(_rank_against_catalog(text, catalog), key=lambda pair: pair[1], reverse=True)
    best_item, best_score = ranked[0]
    best_score = _clamp_score(best_score)
    if best_score <= SIMILARITY_FLOOR:
        return _default_category(), 0.0
    return best_item["Category"], round(best_score, 4)
