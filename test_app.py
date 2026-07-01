import importlib
import os


def setup_module(module):
    # Ensure GATEWAY_TOKEN is set before importing the app module.
    # NOTE: this name is a required pytest/xunit hook — pytest looks for the
    # exact string "setup_module" to auto-run this before tests in the file.
    # Renaming it (e.g. to setupModule) means pytest silently ignores it.
    os.environ["GATEWAY_TOKEN"] = "testtoken"
    # Import the app after setting the env var.
    global app
    import app as appModule

    importlib.reload(appModule)
    app = appModule.app


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
    # User expresses interest in Laravel and realtime.
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": "user-456", "Interests": ["Laravel", "Realtime"], "RecentMessages": ["Looking for websockets examples"]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    # Expect the top recommendation to be Laravel Realtime WebSockets.
    assert data["UserID"] == "user-456"
    assert data["Recommendations"][0]["Category"] == "Laravel Realtime WebSockets"
    assert data["Recommendations"][0]["RelevanceScore"] > 0


def testRecommendUsesRecentMessagesWhenInterestsAreMissing():
    client = app.test_client()
    resp = client.post(
        "/recommend",
        headers={"Authorization": "Bearer testtoken"},
        json={"UserID": "user-789", "RecentMessages": ["I need Java network sockets and stream examples"]},
    )
    assert resp.status_code == 200
    data = resp.get_json()
    assert data["UserID"] == "user-789"
    assert data["Recommendations"][0]["Category"] == "Java Network Sockets"
    assert data["Recommendations"][0]["RelevanceScore"] > 0