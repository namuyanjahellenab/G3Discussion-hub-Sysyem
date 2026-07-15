# Content moderation using local machine learning - no external API needed.
# Two TF-IDF + Naive Bayes classifiers (same technique as catalog.py) are
# trained at import time on a small bundled dataset: one spots spam, one
# spots generic off-topic/casual text. Replies also get a cosine-similarity
# check against the thread they're replying to, so a reply can be flagged
# as irrelevant even if it reads as generically "educational" on its own.

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.naive_bayes import MultinomialNB
from sklearn.pipeline import Pipeline

# Below this cosine similarity to the thread, a reply is considered off-topic.
RELEVANCE_SIMILARITY_FLOOR = 0.05
# Short replies ("thanks!", "makes sense") rarely share vocabulary with the
# thread even when genuinely on-topic, so they skip the similarity check.
SHORT_REPLY_WORD_LIMIT = 4
# With a small training set, borderline predictions are closer to noise than
# signal - require this much confidence before calling something spam, so
# genuinely ambiguous text is allowed through rather than blocked.
SPAM_CONFIDENCE_THRESHOLD = 0.6
# Same reasoning in the other direction: only call text "not educational"
# once the classifier is reasonably sure, so short/generic-but-legitimate
# text (e.g. "question body") isn't blocked on a near-50/50 guess.
NOT_EDUCATIONAL_CONFIDENCE_THRESHOLD = 0.6

# (text, is_spam) pairs used to train the spam classifier.
SPAM_TRAINING_DATA = [
    ("buy cheap laptops now, huge discount click here", 1),
    ("earn $5000 a week working from home, no experience needed", 1),
    ("claim your free prize now, limited time offer", 1),
    ("cheap essays written for you, contact us on whatsapp for a quick deal", 1),
    ("win a free iphone, click this link to claim your prize", 1),
    ("hot deals on textbooks, visit our site for exclusive discount codes", 1),
    ("congratulations you have been selected for a guaranteed loan approval", 1),
    ("increase your instagram followers instantly, dm me for details", 1),
    ("crypto investment opportunity, guaranteed returns, invest today", 1),
    ("act now, offer expires today, limited stock available, buy now", 1),
    ("click here to unlock your free gift card", 1),
    ("make money online fast, join now, no risk involved", 1),
    ("get rich quick with this one simple trick, click the link", 1),
    ("your account has been selected, verify now to claim your reward", 1),
    ("cheap replica watches for sale, message me on whatsapp", 1),
    ("hot singles in your area want to meet you, click here", 1),
    ("free vpn download, click this link now", 1),
    ("sell your assignments here, fast delivery, cheap prices, dm now", 1),
    ("limited time discount on all courses, buy now before it's gone", 1),
    ("work from home and earn thousands weekly, sign up today", 1),
    ("you have won a lottery prize, claim your winnings now", 1),
    ("best forex trading signals, guaranteed profit, join our telegram", 1),
    ("cheap loans approved instantly, no credit check required", 1),
    ("buy followers and likes cheap, fast delivery, click here", 1),
    ("exclusive offer just for you, buy one get one free today only", 1),
    ("how does merge sort work?", 0),
    ("can someone explain normalization in databases", 0),
    ("i'm getting a null pointer exception in my java code, any ideas?", 0),
    ("what's the deadline for the group project submission", 0),
    ("thanks for the explanation, that makes sense now", 0),
    ("does anyone have notes from today's lecture on networking", 0),
    ("i think the time complexity of this algorithm is O(n log n)", 0),
    ("can we meet tomorrow to discuss the assignment", 0),
    ("the lecturer said the exam covers chapters 1 through 5", 0),
    ("i'm struggling with recursion, can someone help explain it", 0),
    ("here's a link to the official python documentation for reference", 0),
    ("great answer, i understand it much better now", 0),
    ("what time does the quiz start today", 0),
    ("i submitted my assignment late, will there be a penalty", 0),
    ("let's form a study group for the database exam", 0),
    ("anyone else finding the tcp/ip section confusing", 0),
    ("i'll share my code so we can review it together", 0),
    ("the library has extra copies of the textbook available", 0),
    ("can you clarify what the professor meant by dynamic programming", 0),
    ("lol anyone up for pizza tonight", 0),
    ("what's everyone doing this weekend", 0),
    ("happy birthday! hope you have a great day", 0),
    ("good morning everyone, ready for the lecture", 0),
    ("see you all in class tomorrow", 0),
    ("congrats on finishing your project, well done", 0),
    ("what's the best restaurant near campus", 0),
    ("anyone free to grab lunch later", 0),
    ("i can't find the assignment submission link", 0),
    ("is the exam open book or closed book", 0),
    ("what time zone is the online lecture in", 0),
    ("i really enjoyed today's class discussion", 0),
    ("where can i download the lecture slides", 0),
    ("our team meeting is at 3pm tomorrow", 0),
    ("i think i found a bug in the starter code", 0),
    ("what's a good ide for python development", 0),
    ("congrats to everyone who passed the midterm", 0),
    ("is attendance mandatory for tomorrow's session", 0),
    ("anyone want to grab coffee before class", 0),
    ("what's the wifi password in the library", 0),
    ("is there parking available near the lecture hall", 0),
    ("can someone lend me a charger for my laptop", 0),
    ("what's the weather like for the field trip", 0),
    ("i missed the bus, will be a few minutes late", 0),
]

# (text, is_educational) pairs used to train the generic relevance classifier
# - used when there's no specific thread to compare a reply against.
EDUCATIONAL_TRAINING_DATA = [
    ("how does merge sort work?", 1),
    ("can someone explain normalization in databases", 1),
    ("i'm getting a null pointer exception in my java code", 1),
    ("what's the deadline for the group project", 1),
    ("does anyone have notes from today's lecture on networking", 1),
    ("i think the time complexity of this algorithm is O(n log n)", 1),
    ("can we meet tomorrow to discuss the assignment", 1),
    ("the lecturer said the exam covers chapters 1 through 5", 1),
    ("i'm struggling with recursion, can someone help explain it", 1),
    ("here's a link to the official python documentation", 1),
    ("what time does the quiz start today", 1),
    ("i submitted my assignment late, will there be a penalty", 1),
    ("let's form a study group for the database exam", 1),
    ("anyone else finding the tcp/ip section confusing", 1),
    ("i'll share my code so we can review it together", 1),
    ("can you clarify what the professor meant by dynamic programming", 1),
    ("how do i fix this sql syntax error in my query", 1),
    ("what's the difference between a stack and a queue", 1),
    ("our group project is about designing a relational database schema", 1),
    ("please review my pull request for the assignment", 1),
    ("lol anyone up for pizza tonight", 0),
    ("what's everyone doing this weekend", 0),
    ("happy birthday! hope you have a great day", 0),
    ("good morning everyone, ready for the lecture", 0),
    ("see you all in class tomorrow", 0),
    ("congrats on finishing your project, well done", 0),
    ("anyone watching the football game tonight", 0),
    ("what's the best restaurant near campus", 0),
    ("i'm so tired today, need coffee", 0),
    ("does anyone know a good movie to watch", 0),
    ("happy new year everyone", 0),
    ("who else is excited for the holidays", 0),
    ("check out this funny meme i found", 0),
    ("what's your favorite music genre", 0),
    ("anyone want to hang out after class", 0),
    ("i love this weather today", 0),
    ("just adopted a new puppy, so cute", 0),
    ("what are your plans for the summer break", 0),
    ("did you watch the new episode last night", 0),
    ("happy weekend everyone, relax and enjoy", 0),
]


def _train_classifier(training_data: list[tuple[str, int]]) -> Pipeline:
    texts = [text for text, _ in training_data]
    labels = [label for _, label in training_data]
    pipeline = Pipeline([
        ("tfidf", TfidfVectorizer(stop_words="english")),
        ("clf", MultinomialNB()),
    ])
    pipeline.fit(texts, labels)
    return pipeline


_spam_classifier = _train_classifier(SPAM_TRAINING_DATA)
_educational_classifier = _train_classifier(EDUCATIONAL_TRAINING_DATA)


def _is_spam(text: str) -> bool:
    if not text or not text.strip():
        return False
    spam_probability = _spam_classifier.predict_proba([text])[0][1]
    return bool(spam_probability > SPAM_CONFIDENCE_THRESHOLD)


def _is_generically_educational(text: str) -> bool:
    if not text or not text.strip():
        return True
    not_educational_probability = _educational_classifier.predict_proba([text])[0][0]
    return bool(not_educational_probability < NOT_EDUCATIONAL_CONFIDENCE_THRESHOLD)


def _is_relevant_to_thread(text: str, context: str) -> bool:
    # Short replies rarely share enough vocabulary with the thread to score
    # well on similarity even when genuinely on-topic, so let them through.
    if len(text.split()) <= SHORT_REPLY_WORD_LIMIT:
        return True

    vectorizer = TfidfVectorizer(stop_words="english")
    matrix = vectorizer.fit_transform([context, text])
    similarity = cosine_similarity(matrix[1], matrix[0])[0][0]
    return bool(similarity > RELEVANCE_SIMILARITY_FLOOR)


def _classify_content(text: str, context: str | None = None) -> dict:
    # context, if given, is the thread the text is replying to - relevance
    # is then judged against that thread instead of generic educational-ness -
    # otherwise any academic-sounding reply would pass regardless of whether
    # it actually relates to this thread.
    if not text or not text.strip():
        return {"is_spam": False, "is_educational": True}

    is_spam = _is_spam(text)

    if context and context.strip():
        is_educational = _is_relevant_to_thread(text, context)
    else:
        is_educational = _is_generically_educational(text)

    return {"is_spam": is_spam, "is_educational": is_educational}


def _is_educational(text: str) -> bool:
    return _classify_content(text)["is_educational"]
