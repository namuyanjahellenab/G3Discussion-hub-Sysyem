# Keyword-based spam detection with stemming and phrase-aware matching.
# Stems both the message and keyword phrases, then requires every word of a
# multi-word phrase to appear together within a small window.

import os
import re

from nltk.stem import PorterStemmer

from config import DEFAULT_SPAM_KEYWORDS, SPAM_PHRASE_MATCH_SLACK

_word_pattern = re.compile(r"[a-zA-Z']+")
_stemmer = PorterStemmer()


def _tokenize_and_stem(text: str) -> list[str]:
    # Lowercase, split into words, and reduce each to its stem (e.g. "buying" -> "buy").
    return [_stemmer.stem(word) for word in _word_pattern.findall(text.lower())]


def _spam_keyword_phrases() -> list[list[str]]:
    raw = os.environ.get("SPAM_KEYWORDS", DEFAULT_SPAM_KEYWORDS)
    phrases = [keyword.strip() for keyword in raw.split(",") if keyword.strip()]
    return [_tokenize_and_stem(phrase) for phrase in phrases]


def _phrase_matches(phrase_stems: list[str], message_stems: list[str]) -> bool:
    # True if every stemmed word in phrase_stems appears together within a small sliding window.
    if len(phrase_stems) == 1:
        return phrase_stems[0] in message_stems

    phrase_set = set(phrase_stems)
    window = len(phrase_stems) + SPAM_PHRASE_MATCH_SLACK
    for start in range(len(message_stems)):
        if phrase_set <= set(message_stems[start:start + window]):
            return True
    return False


def _is_spam(text: str) -> bool:
    message_stems = _tokenize_and_stem(text)
    if not message_stems:
        return False
    return any(_phrase_matches(phrase, message_stems) for phrase in _spam_keyword_phrases())
