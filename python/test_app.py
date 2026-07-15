import importlib
import os


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


def testRecommendScoresNeverExceedOne(monkeypatch):
    monkeypatch.setattr(appModule, "_fetch_user_recent_text", lambda user_id: [])
    monkeypatch.setattr(
        appModule,
        "_fetch_trending_categories",
        lambda group_ids: [{"Category": "Algorithms", "Engagement": 999999}],
    )

    client = app.test_client()
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": 999, "GroupIDs": [1]},
    )
    data = resp.get_json()
    assert all(r["RelevanceScore"] <= 1.0 for r in data["Recommendations"])


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


def testClassifySpam():
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


def testClassifySpamCatchesVerbFormBypass():
    # "buying it now" should match "buy now" once stemmed.
    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "I'm buying it now before the deal ends"},
    )
    data = resp.get_json()
    assert data["IsFiltered"] is True


def testClassifySpamCatchesPluralBypass():
    # "loan approvals" should match "loan approved" via shared stem.
    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "Your loan approvals are ready, check now"},
    )
    data = resp.get_json()
    assert data["IsFiltered"] is True


def testClassifyDoesNotFlagSingleWordFromTwoWordPhrase():
    # "now" alone must not trigger - all words of a multi-word phrase are required.
    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={"MessageText": "Can we meet now to discuss the assignment?"},
    )
    data = resp.get_json()
    assert data["IsFiltered"] is False
    assert data["PredictedCategory"] != "Spam/Filtered Content"


def testClassifyDoesNotFlagUnrelatedWordsFarApart():
    # "buy" and "now" both appear but far apart, so the sliding window should not match.
    client = app.test_client()
    resp = client.post(
        "/classify",
        headers={"Authorization": "Bearer testtoken"},
        json={
            "MessageText": (
                "I want to buy a new textbook for the algorithms course. "
                "My lecturer said the library restocks it sometime around now."
            )
        },
    )
    data = resp.get_json()
    assert data["IsFiltered"] is False


def testAuthFailure():
    client = app.test_client()
    resp = client.post(
        "/classify",
        json={"MessageText": "No auth header"},
    )
    assert resp.status_code == 401


def testRecommendSuccess():
    client = app.test_client()
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": "user-123"},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["UserID"] == "user-123"
    assert isinstance(data["Recommendations"], list)


def testRecommendDynamic():
    client = app.test_client()
    # User expresses interest in algorithms/data structures topics.
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": "user-456", "Interests": ["Algorithms", "Recursion"], "RecentMessages": ["Looking for sorting and complexity examples"]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    # Expect the top recommendation to be Algorithms.
    assert data["UserID"] == "user-456"
    assert data["Recommendations"][0]["Category"] == "Algorithms"
    assert data["Recommendations"][0]["RelevanceScore"] > 0


def testRecommendUsesRecentMessagesWhenInterestsAreMissing():
    client = app.test_client()
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": "user-789", "RecentMessages": ["I need help with network sockets and tcp protocols"]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["UserID"] == "user-789"
    assert data["Recommendations"][0]["Category"] == "Networks"
    assert data["Recommendations"][0]["RelevanceScore"] > 0


def testRecommendColdStartUsesTrending(monkeypatch):
    # No DB activity for this user, but trending data exists - should rank by engagement.
    monkeypatch.setattr(appModule, "_fetch_user_recent_text", lambda user_id: [])
    monkeypatch.setattr(
        appModule,
        "_fetch_trending_categories",
        lambda group_ids: [
            {"Category": "Algorithms", "Engagement": 10},
            {"Category": "Databases", "Engagement": 4},
        ],
    )

    client = app.test_client()
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": 999, "GroupIDs": [1, 2], "Interests": ["Algorithms"]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["Mode"] == "trending"
    assert data["Recommendations"][0]["Category"] == "Algorithms"
    assert data["Recommendations"][0]["RelevanceScore"] == 1.0
    assert data["Recommendations"][1]["Category"] == "Databases"


def testRecommendFallsBackToPersonalizedWhenNoTrendingSignal(monkeypatch):
    # No trending signal either - should fall back to Interests/RecentMessages.
    monkeypatch.setattr(appModule, "_fetch_user_recent_text", lambda user_id: [])
    monkeypatch.setattr(appModule, "_fetch_trending_categories", lambda group_ids: [])

    client = app.test_client()
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": 999, "GroupIDs": [1], "RecentMessages": ["database schema design and sql joins"]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["Recommendations"][0]["Category"] == "Databases"


def testRecommendGroupsSuccess(monkeypatch):
    monkeypatch.setattr(
        appModule,
        "_fetch_group_suggestions",
        lambda user_id, exclude_ids: [
            {"GroupID": 1, "GroupName": "Algorithms", "MemberCount": 10, "RecentActivity": 5},
            {"GroupID": 2, "GroupName": "Databases", "MemberCount": 3, "RecentActivity": 1},
        ],
    )

    client = app.test_client()
    resp = client.post(
        "/recommend-groups",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": 999, "GroupIDs": [5]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["SuggestedGroups"][0]["GroupID"] == 1
    assert data["SuggestedGroups"][0]["InteractionScore"] == 1.0
    assert data["SuggestedGroups"][1]["GroupID"] == 2


def testRecommendGroupsAuthFailure():
    client = app.test_client()
    resp = client.post("/recommend-groups", json={"UserID": 999})
    assert resp.status_code == 401


def testRecommendGroupsDbUnavailableFallsBackToEmpty(monkeypatch):
    monkeypatch.setattr(appModule, "_run_query", lambda *args, **kwargs: None)

    client = app.test_client()
    resp = client.post(
        "/recommend-groups",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": 999},
    )
    assert resp.status_code == 200
    assert resp.get_json()["SuggestedGroups"] == []


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