# Builds a TF-IDF category catalog and classifies/ranks text against it.
# The catalog is built entirely from live Topic data in the database (no
# hardcoded seed content) and cached for a while to avoid re-querying on
# every single /classify call.

import time
from typing import Any

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from config import CATALOG_TTL_SECONDS, SIMILARITY_FLOOR, _default_category
from db import _fetch_live_categories

_catalog_cache: dict[str, Any] = {"built_at": 0.0, "catalog": None}


def _clamp_score(score: float) -> float:
    # Keeps a similarity or ratio score within [0, 1], since floating point
    # rounding can occasionally push a perfect match slightly past 1.0.
    return max(0.0, min(float(score), 1.0))


def _build_catalog() -> list[dict[str, str]]:
    # Building the catalog means querying the database, which would be
    # wasteful on every single /classify call. Caching the result for
    # CATALOG_TTL_SECONDS lets many requests reuse it before it's rebuilt.
    now = time.time()
    cached = _catalog_cache.get("catalog")
    if cached is not None and (now - _catalog_cache.get("built_at", 0)) < CATALOG_TTL_SECONDS:
        return cached

    merged: dict[str, str] = {}
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
    # Returns (entry, score) pairs in the catalog's original order. The
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
    # Returns the best matching category for text, or DEFAULT_CATEGORY at
    # score 0.0 if even the closest match is too weak to trust (below
    # SIMILARITY_FLOOR), or if there's no catalog to compare against at all
    # yet (a fresh install with no categorized topics, or the DB being
    # unreachable). Same fallback value either way, just reached without
    # ever calling into TF-IDF/cosine similarity on an empty catalog.
    catalog = _build_catalog()
    if not catalog:
        return _default_category(), 0.0

    ranked = sorted(_rank_against_catalog(text, catalog), key=lambda pair: pair[1], reverse=True)
    best_item, best_score = ranked[0]
    best_score = _clamp_score(best_score)
    if best_score <= SIMILARITY_FLOOR:
        return _default_category(), 0.0
    return best_item["Category"], round(best_score, 4)