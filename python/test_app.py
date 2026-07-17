import importlib
import os

import spam


def setup_module(module):
    # pytest auto-runs this exact function name before tests in the file.
    os.environ["GATEWAY_TOKEN"] = "testtoken"
    os.environ["GATEWAY_EXPECTED_TOKEN"] = "testtoken"
    # Use a DB-unreachable host so test behavior stays deterministic.
    os.environ["DB_HOST"] = "127.0.0.1"
    os.environ["DB_PORT"] = "1"
    # Import the app after setting the env vars.
    global app, appModule
    import app as appModule

    importlib.reload(appModule)
    app = appModule.app


def testClampScoreNeverExceedsUnitRange():
    assert appModule._clamp_score(1.0000000002) == 1.0
    assert appModule._clamp_score(1.5) == 1.0
    assert appModule._clamp_score(-0.3) == 0.0
    assert appModule._clamp_score(0.42) == 0.42


def testClassifySuccess():
    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "Hello there", "MessageID": 10},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["MessageID"] == 10
    assert data["IsFiltered"] is False
    assert data["PredictedCategory"] == "General Chat"


def testClassifySpam(monkeypatch):
    # /classify itself doesn't care how _classify_content decides - that's
    # spam.py's job (covered below). This just checks the short-circuit wiring.
    monkeypatch.setattr(appModule, "_classify_content", lambda text, context=None: {"is_spam": True, "is_educational": True})

    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "Buy crypto now!"},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["IsFiltered"] is True
    assert data["PredictedCategory"] == "Spam/Filtered Content"
    assert data["IsEducational"] is True


def testClassifyNotSpamSkipsShortCircuit(monkeypatch):
    monkeypatch.setattr(appModule, "_classify_content", lambda text, context=None: {"is_spam": False, "is_educational": True})

    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "How does merge sort work?"},
    )
    data = resp.get_json()
    assert data["IsFiltered"] is False
    assert data["PredictedCategory"] != "Spam/Filtered Content"
    assert data["IsEducational"] is True


def testClassifyNonEducationalIsReportedEvenWhenNotSpam(monkeypatch):
    monkeypatch.setattr(appModule, "_classify_content", lambda text, context=None: {"is_spam": False, "is_educational": False})

    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "lol what a nice day outside"},
    )
    data = resp.get_json()
    assert data["IsFiltered"] is False
    assert data["IsEducational"] is False


def testClassifyPassesContextThrough(monkeypatch):
    received = {}

    def fake_classify(text, context=None):
        received["text"] = text
        received["context"] = context
        return {"is_spam": False, "is_educational": False}

    monkeypatch.setattr(appModule, "_classify_content", fake_classify)

    client = app.test_client()
    client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "pizza tonight?", "Context": "Sorting Algorithms Help\nHow does merge sort work?"},
    )

    assert received["text"] == "pizza tonight?"
    assert received["context"] == "Sorting Algorithms Help\nHow does merge sort work?"


def testClassifyContentBlankTextReturnsDefaultVerdict():
    assert spam._classify_content("") == {"is_spam": False, "is_educational": True}
    assert spam._classify_content("   ") == {"is_spam": False, "is_educational": True}
    assert spam._is_spam("") is False


def testIsSpamDetectsObviousSpam():
    assert spam._is_spam("click here to claim your free prize now, limited offer") is True
    assert spam._is_spam("earn $5000 a week working from home, sign up today") is True


def testIsSpamDoesNotFlagOrdinaryAcademicText():
    assert spam._is_spam("how does merge sort work?") is False
    assert spam._is_spam("can someone explain normalization in databases") is False


def testIsEducationalDetectsAcademicText():
    assert spam._is_generically_educational("what's the time complexity of binary search?") is True
    assert spam._is_generically_educational("i'm stuck on the recursion assignment, any tips?") is True


def testIsEducationalFlagsCasualChitChat():
    assert spam._is_generically_educational("lol anyone up for pizza tonight") is False
    assert spam._is_generically_educational("happy birthday, hope you have a great day") is False


def testClassifyContentWithoutContextUsesGenericEducationalCheck():
    verdict = spam._classify_content("what's the best restaurant near campus")
    assert verdict == {"is_spam": False, "is_educational": False}


def testReplyRelevantToThreadIsNotFlagged():
    context = "Sorting Algorithms Help\nHow does merge sort work?"
    verdict = spam._classify_content(
        "Merge sort splits the array in half recursively, then merges the sorted halves back together.",
        context=context,
    )
    assert verdict == {"is_spam": False, "is_educational": True}


def testReplyIrrelevantToThreadIsFlaggedEvenIfGenericallyEducational():
    # "Explain recursion" reads as academic on its own, but this thread is
    # about database normalization - it should still be flagged as irrelevant.
    context = "Database Design Help\nCan someone explain third normal form?"
    verdict = spam._classify_content(
        "Recursion is when a function calls itself to solve smaller subproblems, commonly used in sorting algorithms.",
        context=context,
    )
    assert verdict == {"is_spam": False, "is_educational": False}


def testShortReplyToThreadSkipsRelevanceCheck():
    # Short acknowledgments rarely share vocabulary with the thread even
    # when genuinely on-topic, so they're allowed through regardless.
    context = "Sorting Algorithms Help\nHow does merge sort work?"
    verdict = spam._classify_content("Thanks, makes sense!", context=context)
    assert verdict == {"is_spam": False, "is_educational": True}


def testSpamReplyToThreadIsStillCaughtRegardlessOfRelevance():
    context = "Sorting Algorithms Help\nHow does merge sort work?"
    verdict = spam._classify_content(
        "buy cheap laptops now, huge discount click here",
        context=context,
    )
    assert verdict["is_spam"] is True


def testAuthFailure():
    client = app.test_client()
    resp = client.post(
        "/classify",
        json={"MessageText": "No auth header"},
    )
    assert resp.status_code == 401


def testRecommendTopicsScoresVaryByTitle():
    client = app.test_client()
    resp = client.post(
        "/recommend-topics",
        headers={"Authorization": "Bearer testtoken"},
        json={
            "UserID": 999,
            "Interests": ["Algorithms"],
            "RecentMessages": ["Looking for sorting and complexity examples"],
            "Topics": [
                {"TopicID": 1, "Title": "Merge sort walkthrough", "Category": "Algorithms"},
                {"TopicID": 2, "Title": "Binary search trees explained", "Category": "Algorithms"},
                {"TopicID": 3, "Title": "Planning the end of year party", "Category": "General Chat"},
            ],
        },
    )
    assert resp.status_code == 200
    data = resp.get_json()
    scores = {row["TopicID"]: row["RelevanceScore"] for row in data["TopicScores"]}
    # Two topics share a category but have different titles - they must not
    # collapse onto the same duplicated score the way category-only ranking did.
    assert scores[1] != scores[2]
    assert scores[1] > scores[3]
    assert scores[2] > scores[3]


def testRecommendTopicsNoSignalReturnsZeroForEveryTopic():
    client = app.test_client()
    resp = client.post(
        "/recommend-topics",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": 999, "Topics": [{"TopicID": 1, "Title": "Anything", "Category": "Algorithms"}]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["TopicScores"] == [{"TopicID": 1, "RelevanceScore": 0.0}]


def testRecommendTopicsAuthFailure():
    client = app.test_client()
    resp = client.post("/recommend-topics", json={"UserID": 999, "Topics": []})
    assert resp.status_code == 401


def testTrendingGroupsSuccess(monkeypatch):
    monkeypatch.setattr(
        appModule,
        "_fetch_trending_groups",
        lambda: [
            {"GroupID": 1, "GroupName": "Algorithms", "MemberCount": 10, "RecentActivity": 5},
            {"GroupID": 2, "GroupName": "Databases", "MemberCount": 3, "RecentActivity": 1},
        ],
    )

    client = app.test_client()
    resp = client.post("/trending-groups", headers={"Authorization": "Bearer testtoken"})
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["TrendingGroups"][0]["GroupID"] == 1
    assert data["TrendingGroups"][0]["InteractionScore"] == 1.0
    assert data["TrendingGroups"][1]["GroupID"] == 2


def testTrendingGroupsAuthFailure():
    client = app.test_client()
    resp = client.post("/trending-groups")
    assert resp.status_code == 401


def testTrendingGroupsDbUnavailableFallsBackToEmpty(monkeypatch):
    # _run_query lives in db.py and is called from there internally, not
    # imported into app.py - patching it on appModule was a no-op that
    # happened to pass only because the test env's DB_HOST is already
    # unreachable, not because of the patch.
    import db
    monkeypatch.setattr(db, "_run_query", lambda *args, **kwargs: None)

    client = app.test_client()
    resp = client.post("/trending-groups", headers={"Authorization": "Bearer testtoken"})
    assert resp.status_code == 200
    assert resp.get_json()["TrendingGroups"] == []


def testExportTopicPdfAuthFailure():
    client = app.test_client()
    resp = client.post("/export-topic-pdf", json={"TopicID": 1})
    assert resp.status_code == 401


def testExportTopicPdfSuccess(monkeypatch):
    monkeypatch.setattr(
        appModule,
        "_fetch_topic_export_data",
        lambda topic_id: {
            "topic": {"TopicID": topic_id, "Title": "Sorting Algorithms Help", "GroupName": "Algorithms"},
            "posts": [
                {
                    "PostID": 1,
                    "Content": "How does merge sort work?",
                    "Attachment": None,
                    "AuthorName": "kabaata",
                    "Replies": [{"ReplyContent": "It's divide and conquer.", "AuthorName": "lubwama"}],
                }
            ],
        },
    )

    client = app.test_client()
    resp = client.post(
        "/export-topic-pdf",
        headers={"Authorization": "Bearer testtoken"},
        json={"TopicID": 1},
    )
    assert resp.status_code == 200
    assert resp.content_type == "application/pdf"
    assert resp.data[:4] == b"%PDF"
    assert "attachment" in resp.headers.get("Content-Disposition", "")


def testExportTopicPdfNotFound(monkeypatch):
    monkeypatch.setattr(appModule, "_fetch_topic_export_data", lambda topic_id: {"topic": None, "posts": []})

    client = app.test_client()
    resp = client.post(
        "/export-topic-pdf",
        headers={"Authorization": "Bearer testtoken"},
        json={"TopicID": 999},
    )
    assert resp.status_code == 404


def testExportTopicPdfDbUnavailable(monkeypatch):
    monkeypatch.setattr(appModule, "_fetch_topic_export_data", lambda topic_id: None)

    client = app.test_client()
    resp = client.post(
        "/export-topic-pdf",
        headers={"Authorization": "Bearer testtoken"},
        json={"TopicID": 1},
    )
    assert resp.status_code == 503


def testTopicShareLinksAuthFailure():
    client = app.test_client()
    resp = client.post("/topic-share-links", json={"TopicID": 1, "BaseUrl": "http://example.test"})
    assert resp.status_code == 401


def testTopicShareLinksSuccess(monkeypatch):
    monkeypatch.setattr(
        appModule,
        "_fetch_topic_share_data",
        lambda topic_id: {"title": "Sorting Algorithms Help", "reply_count": 3},
    )

    client = app.test_client()
    resp = client.post(
        "/topic-share-links",
        headers={"Authorization": "Bearer testtoken"},
        json={"TopicID": 1, "BaseUrl": "http://example.test"},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["ShareUrl"] == "http://example.test/topics/1"
    assert "3 replies" in data["ShareText"]
    assert "wa.me" in data["Links"]["whatsapp"]
    assert "twitter.com" in data["Links"]["twitter"]
    assert "facebook.com" in data["Links"]["facebook"]
    assert data["Links"]["email"].startswith("mailto:")


def testTopicShareLinksMissingParams():
    client = app.test_client()
    resp = client.post(
        "/topic-share-links",
        headers={"Authorization": "Bearer testtoken"},
        json={"TopicID": 1},
    )
    assert resp.status_code == 400