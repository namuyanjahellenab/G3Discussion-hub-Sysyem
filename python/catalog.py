# TF-IDF category catalog and classification/ranking against it.
# The catalog is a static seed list enriched with live Topic data, TTL-cached.

import time
from typing import Any

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from config import CATALOG_TTL_SECONDS, SIMILARITY_FLOOR, _default_category
from db import _fetch_live_categories

# Static seed catalog, bootstraps classification and is the fallback if the DB is unreachable.
# Categories match the app's seeded groups (database/seeders/GroupSeeder.php).
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
    # Clamp a similarity/ratio score to [0, 1] to guard against float rounding past 1.0.
    return max(0.0, min(float(score), 1.0))


def _build_catalog() -> list[dict[str, str]]:
    # Static seed catalog enriched with live Topic categories, TTL-cached.
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
    corpus = [item["Text"] for item in catalog] + [query_text]
    vectorizer = TfidfVectorizer(stop_words="english")
    matrix = vectorizer.fit_transform(corpus)
    similarities = cosine_similarity(matrix[-1], matrix[:-1])[0]
    return list(zip(catalog, similarities))


def _rank_texts(query_text: str, texts: list[str]) -> list[float]:
    # Cosine-similarity-rank arbitrary texts (e.g. topic titles) against a
    # query - for callers ranking specific items rather than the fixed
    # category catalog. Returns one score per text, same order as given.
    corpus = texts + [query_text]
    vectorizer = TfidfVectorizer(stop_words="english")
    matrix = vectorizer.fit_transform(corpus)
    similarities = cosine_similarity(matrix[-1], matrix[:-1])[0]
    return [_clamp_score(score) for score in similarities]


def _classify_category(text: str) -> tuple[str, float]:
    catalog = _build_catalog()
    ranked = sorted(_rank_against_catalog(text, catalog), key=lambda pair: pair[1], reverse=True)
    best_item, best_score = ranked[0]
    best_score = _clamp_score(best_score)
    if best_score <= SIMILARITY_FLOOR:
        return _default_category(), 0.0
    return best_item["Category"], round(best_score, 4)
