# Content moderation using local machine learning - no external API needed.
# Two TF-IDF + Naive Bayes classifiers are trained at import time on a small
# bundled dataset: one detects spam, the other detects generic off-topic or
# casual text. Replies also get a cosine-similarity check against their own
# thread, so a reply can still be flagged as irrelevant even if it reads as
# educational on its own.

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

# Same idea as SPAM_CONFIDENCE_THRESHOLD, but in the other direction: only
# call text "not educational" once the classifier is fairly confident, not
# on a near-50/50 guess. Casual chit-chat reliably scores well above 0.6,
# and genuine academic questions score well below it - but that gap only
# holds because the training data below covers a wide range of legitimate
# phrasing. Widening the gap means adding more training examples, not
# changing this number.
NOT_EDUCATIONAL_CONFIDENCE_THRESHOLD = 0.6

# (text, is_spam) pairs used to train the spam classifier.
SPAM_TRAINING_DATA = [
    # Classic promotional/scam language: discounts, prizes, "click here",
    # urgency, and requests to move the conversation off-platform.
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
    # Scam variants using different platforms and hooks (scholarships,
    # trading bots, fake internships, package/account phishing) - covering
    # more phrasing than "buy/discount/click" alone keeps the classifier from
    # missing scams that use different filler words to make the same pitch.
    ("grab this amazing discount on essay writing services, message us on telegram", 1),
    ("you've been selected for a free scholarship, click this link to claim it now", 1),
    ("double your money in a week with this crypto trading bot, invest today", 1),
    ("cheap loans available instantly, no paperwork, apply through this link", 1),
    ("get thousands of tiktok followers overnight, dm for pricing", 1),
    ("flash sale on branded shoes, hit this link before stock runs out", 1),
    ("work only 2 hours a day and earn in dollars, join our whatsapp group", 1),
    ("premium software licenses at 90 percent off, contact us to purchase", 1),
    ("your account will be suspended, verify your details here immediately", 1),
    ("hi dear, i'm interested in getting to know you better, message me", 1),
    ("paid internship guaranteed no interview needed, apply through this link", 1),
    ("urgent, you have unclaimed funds waiting, click to verify your identity", 1),
    ("get a free credit score boost instantly, sign up with your bank details", 1),
    ("earn passive income trading binary options, guaranteed daily payout", 1),
    ("cheap assignment help, we write it for you, fast turnaround, whatsapp us", 1),
    ("your package could not be delivered, click here to reschedule", 1),
    ("this stock is about to explode, buy in now before it is too late", 1),
    ("your subscription payment failed, update your card info here now", 1),
    ("we noticed unusual activity, confirm your password here to secure your account", 1),
    # Everyday academic and social messages that are not spam.
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
    # Legitimate messages that mention sharing a link or an upcoming
    # deadline - included so urgency and links alone are not treated as
    # spam signals; the classifier needs real academic context to weigh
    # against them.
    ("here is the link, click it to access the assignment brief", 0),
    ("act now if you want a spot in the limited practical lab slots", 0),
    # Legitimate questions about price and budget for course materials -
    # words like "cheap", "buy", and "discount" otherwise only appear on
    # the spam side, which made genuine cost-related questions read as
    # spam themselves.
    ("where can i buy a cheap scientific calculator for the physics test", 0),
    ("does anyone know a budget-friendly laptop good for coding assignments", 0),
    ("is there a discount for students at the campus bookstore", 0),
    ("can someone recommend an affordable edition of the textbook for this course", 0),
    ("what's a good budget laptop for computer science students", 0),
]

# (text, is_educational) pairs used to train the generic relevance
# classifier. Used whenever there's no specific thread to compare a reply
# against - e.g. the opening post of a brand new topic (see storeTopic() on
# the Laravel side). Deliberately spans many subjects, not just computer
# science, since a set narrowly focused on one subject gives the classifier
# an unreliable, near-coin-flip verdict on any question from another course.
EDUCATIONAL_TRAINING_DATA = [
    # Computer science and algorithms.
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
    # Physics, chemistry, biology, and mathematics.
    ("can someone explain the difference between mitosis and meiosis", 1),
    ("i don't understand how to balance this chemical equation", 1),
    ("what's the formula for calculating momentum in this problem set", 1),
    ("how do you derive the quadratic formula from completing the square", 1),
    ("can someone walk me through this integration by parts question", 1),
    ("i'm confused about newton's third law in this practical report", 1),
    ("does anyone know which textbook chapter covers cell respiration", 1),
    ("what's the difference between a covalent and ionic bond", 1),
    # Humanities, business, and academic writing.
    ("how should i structure the argument for my history essay", 1),
    ("can someone give feedback on my thesis statement for the literature paper", 1),
    ("what's the citation format the lecturer wants for this assignment", 1),
    ("i'm not sure how to calculate net present value for this finance question", 1),
    ("does anyone have the reading list for next week's economics seminar", 1),
    ("what's the difference between qualitative and quantitative research methods", 1),
    ("can someone explain supply and demand curves for the economics test", 1),
    # Software Engineering course topics specifically (design patterns,
    # agile, testing, version control, requirements, architecture) - a named
    # group in this app's own seeded course list, so it warrants dedicated
    # coverage rather than relying on the general algorithms examples above.
    ("can someone explain the difference between the factory and singleton design patterns", 1),
    ("what's the difference between scrum and kanban for this agile assignment", 1),
    ("i'm not sure how to write unit tests for this method, any tips", 1),
    ("how do i resolve this merge conflict in git for the group project", 1),
    ("can someone explain what a use case diagram should include for the requirements doc", 1),
    ("what's the difference between unit testing and integration testing", 1),
    ("i'm stuck writing the software requirements specification for our assignment", 1),
    ("how should we structure the sprint backlog for this project milestone", 1),
    ("does anyone have a good example of a UML class diagram for this exercise", 1),
    ("what's the point of dependency injection in this software architecture module", 1),
    ("can someone review my pull request before the code review deadline", 1),
    ("i'm confused about the difference between waterfall and agile methodology", 1),
    ("how do we set up continuous integration for our team's repository", 1),
    ("what should go in the test plan document for this software engineering assignment", 1),
    # General programming concepts - fundamentals, OOP, web/mobile
    # development, APIs, debugging. A beginner's question about basic
    # syntax shares little vocabulary with the algorithms or software-
    # engineering examples above, so it needs its own coverage.
    ("what's the difference between a class and an object in OOP", 1),
    ("can someone explain how inheritance and polymorphism work with an example", 1),
    ("why does my for loop keep running one extra time", 1),
    ("what's the difference between pass by value and pass by reference", 1),
    ("i keep getting an index out of bounds error, what am i doing wrong", 1),
    ("can someone explain the difference between == and === in javascript", 1),
    ("how do i fix this undefined is not a function error in my code", 1),
    ("what's the difference between an array and a linked list", 1),
    ("how do promises and async/await work in javascript", 1),
    ("can someone explain what a rest api endpoint actually does", 1),
    ("i'm not sure how to handle exceptions properly in this function", 1),
    ("what's the difference between a compiler and an interpreter", 1),
    ("how does garbage collection work in java", 1),
    ("can someone explain how to use a dictionary versus a list in python", 1),
    ("why is my variable returning null when i debug this method", 1),
    ("what's the difference between local and global scope in this code", 1),
    ("how do i connect my app to the database using this ORM", 1),
    ("can someone explain how react state and props work", 1),
    ("what's the best way to structure the layout for this mobile app assignment", 1),
    ("i'm getting a syntax error on this line and can't figure out why", 1),
    # Networks, security, and systems - broader than the original
    # algorithms-heavy examples above.
    ("what's the difference between tcp and udp in this networking module", 1),
    ("how does public key encryption actually work", 1),
    ("i'm stuck configuring the firewall rules for this systems assignment", 1),
    ("can someone explain how dns resolution works step by step", 1),
    ("what's causing this deadlock in my operating systems lab", 1),
    # Formal or longer phrasing, not just short casual questions.
    ("i would appreciate some clarification on how the grading rubric works for this course", 1),
    ("could someone please explain the requirements for the final project submission", 1),
    ("i have been struggling to understand this week's lecture material and would like some guidance", 1),
    ("is there a possibility of getting an extension given the technical issues with the portal", 1),
    ("i wanted to ask whether the practical exam covers material from the previous semester", 1),
    # General study logistics that apply across any subject.
    ("can we schedule a study session before the midterm", 1),
    ("does anyone have a copy of last year's past paper for this course", 1),
    ("what room is the tutorial being held in this week", 1),
    ("is the assignment submission through the portal or by email", 1),
    ("i think there might be an error in question 3 of the problem set", 1),
    # Jokes and humor - clearly casual, but easy to mistake for educational
    # content when they mention a course-related word (e.g. a programming
    # pun), which is why they need explicit examples of their own.
    ("why do programmers prefer dark mode? because light attracts bugs", 0),
    ("why was six afraid of seven? because seven eight nine", 0),
    ("i told a joke about udp but i'm not sure if it landed", 0),
    ("why did the chicken cross the road? to get away from group project drama", 0),
    ("what do you call a fish with no eyes? a fsh", 0),
    ("knock knock, who's there, not your homework apparently", 0),
    ("my computer told me a joke, it said 404 joke not found", 0),
    ("why did the developer go broke? because they used up all their cache", 0),
    ("i'm not lazy, i'm just in energy-saving mode until the next lecture", 0),
    ("here's a dad joke to survive finals week, you're welcome", 0),
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
    ("does anyone want to go shopping this saturday", 0),
    ("what's a good gift idea for a birthday party", 0),
    ("i can't believe how expensive concert tickets are these days", 0),
    ("anyone else stuck in this traffic right now", 0),
    ("what's your favorite way to relax after a long week", 0),
    # More campus/social chatter, so "campus" stays anchored as a casual
    # word here and doesn't drift toward "educational" just because it also
    # appears in legitimate study-logistics questions elsewhere.
    ("any good spots near campus to hang out this evening", 0),
    ("what's the cheapest place to eat around campus", 0),
    ("is the campus wifi down for anyone else right now", 0),
    ("what's the atmosphere like at the campus social events", 0),
    ("does the campus shuttle run this late in the evening", 0),
    ("any recommendations for a good salon near campus", 0),
    ("what's everyone wearing to the campus event this weekend", 0),
    ("is the campus gym open on sundays", 0),
    ("anyone know a good barber around campus", 0),
    ("what time does the campus cafeteria close today", 0),
    ("just saw something funny on campus today", 0),
    ("is there a match on this weekend, anyone watching", 0),
    ("what's the weather forecast for the weekend trip", 0),
    ("anyone up for a game later this evening", 0),
    ("congrats on your birthday, have an amazing one", 0),
    ("what's a good playlist for relaxing after a long week", 0),
    ("looking forward to the holidays, just want to rest", 0),
    ("anyone want to share a taxi into town later", 0),
    # Additional casual chatter with more varied wording, so the classifier
    # recognizes social phrasing beyond the specific examples above.
    ("who's up for suya and drinks after work today", 0),
    ("anyone down for grilled meat and drinks after work today", 0),
    ("just binged the whole new season, no spoilers please", 0),
    ("my dog just learned a new trick, so proud", 0),
    ("anyone got tickets for the concert this friday", 0),
    ("what's the vibe like at that new lounge downtown", 0),
    ("i can't stop laughing at this tiktok trend", 0),
    ("does anyone know a good place to get a haircut nearby", 0),
    ("just got back from the gym, absolutely drained", 0),
    ("what's your go to comfort food after a rough day", 0),
    ("anyone else obsessed with this new phone release", 0),
    ("planning a road trip this long weekend, any tips", 0),
    ("what's the best way to unwind after a stressful week", 0),
    ("found this hilarious meme, had to share it", 0),
    ("anyone free for a game night this saturday", 0),
    ("just adopted a kitten, need name suggestions", 0),
    ("what's everyone's plans for new year's eve", 0),
    ("the traffic today was absolutely brutal", 0),
    ("who's got recommendations for a good skincare routine", 0),
    ("what's the funniest thing that happened to you this week", 0),
    ("anyone binge watching anything good right now", 0),
    ("just got my nails done, feeling fancy", 0),
    ("what's a good podcast to listen to on the commute", 0),
    ("anyone else's phone battery dying way too fast lately", 0),
    ("just tried bubble tea for the first time, obsessed", 0),
    # Legitimate academic logistics that happen to use "weekend", "campus",
    # or "budget" - these words otherwise only appear in the casual/social
    # examples above, so genuine questions using them (e.g. "is the library
    # open this weekend for revision") shared more vocabulary with chit-chat
    # than with any educational example.
    ("is the library open this weekend so i can revise for the exam", 1),
    ("what time does the campus lab close before the weekend deadline", 1),
    ("is attendance required for the tutorial happening this weekend", 1),
    ("will the assignment portal be open over the weekend for submissions", 1),
    ("does the campus workshop run into the weekend for final year students", 1),
    ("what room on campus is the revision session held in this week", 1),
    ("is the campus computer lab open on weekends before exams", 1),
    ("what's a good budget laptop for running programming assignments", 1),
    ("any recommendation for a budget textbook edition for this course", 1),
    ("is there a cheaper edition of the recommended textbook available", 1),
    ("what documents do i need for the internship application through the department", 1),
    # These two match the exact phrasing of casual examples above ("cheapest
    # place... campus" and "campus event... weekend") closely enough that a
    # direct academic version of each was needed to break the tie.
    ("what's the cheapest place to buy textbooks near campus", 1),
    ("is attendance mandatory for the campus event this weekend", 1),
]


def _train_classifier(training_data: list[tuple[str, int]]) -> Pipeline:
    # Fits a TF-IDF + Naive Bayes pipeline on (text, label) pairs. Called
    # once at import time for each of the two classifiers below - training
    # is fast enough on this small dataset to not need caching to disk.
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
    # Empty text is never spam - only classify once there's something to
    # score, and require SPAM_CONFIDENCE_THRESHOLD before calling it spam.
    if not text or not text.strip():
        return False
    spam_probability = _spam_classifier.predict_proba([text])[0][1]
    return bool(spam_probability > SPAM_CONFIDENCE_THRESHOLD)


def _is_generically_educational(text: str) -> bool:
    # Used when there's no specific thread to compare against (see
    # _classify_content). Empty text defaults to "educational" so a blank
    # message isn't blocked on a moderation technicality.
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
    # is then judged against that thread instead of generic educational-ness,
    # so a reply must actually relate to the thread rather than merely
    # sounding academic on its own.
    if not text or not text.strip():
        return {"is_spam": False, "is_educational": True}

    is_spam = _is_spam(text)

    if context and context.strip():
        is_educational = _is_relevant_to_thread(text, context)
    else:
        is_educational = _is_generically_educational(text)

    return {"is_spam": is_spam, "is_educational": is_educational}
