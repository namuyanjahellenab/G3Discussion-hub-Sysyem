package com.discussionhub.client.utils;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.model.SyncQueueItem;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class DeltaSyncService {

    // TODO: confirm base path/port against the real Laravel deployment
    private static final String BASE_URL = "http://127.0.0.1:8000/api";
    private static final String PUSH_PATH = "/sync/push";
    private static final String PULL_PATH = "/sync/pull";

    private static final int CONNECT_TIMEOUT_MS = 3000;
    private static final int READ_TIMEOUT_MS = 5000;

    private final DatabaseManager dbManager;

    public DeltaSyncService(DatabaseManager dbManager) {
        this.dbManager = dbManager;
    }

    public void synchronizeLocalChanges() {

        if (!NetworkUtil.isNetworkAvailable()) {
            System.out.println("[Sync] No connectivity. Staying in Offline Mode.");
            dbManager.updateDeviceSyncStatus("Offline");
            return;
        }

        dbManager.updateDeviceSyncStatus("Syncing");

        boolean pushOk = pushPendingChanges();
        boolean pullOk = pullServerChanges();

        dbManager.updateDeviceSyncStatus("Online");

        if (pushOk && pullOk) {
            System.out.println("[Sync] Sync complete.");
        } else {
            System.out.println("[Sync] Sync cycle finished with at least one error.");
        }
    }

    /** Pushes records oldest-first, stopping at the first failure so later
     *  records (which may depend on this one existing server-side) don't
     *  get confirmed out of order. */
    public boolean pushPendingChanges() {
        List<SyncQueueItem> pendingChanges = dbManager.getPendingChanges();

        if (pendingChanges.isEmpty()) {
            return true;
        }

        for (SyncQueueItem item : pendingChanges) {
            boolean success = sendPayloadToServer(item.getPayload());

            if (success) {
                dbManager.markSyncQueueItemAsSynced(item.getSyncQueueId());
            } else {
                System.err.println("[Sync] Push failed for queue id " + item.getSyncQueueId());
                return false;
            }
        }
        return true;
    }

    private boolean sendPayloadToServer(String jsonPayload) {
        try {
            URL url = new URL(BASE_URL + PUSH_PATH);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();

            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json; utf-8");
            conn.setRequestProperty("Accept", "application/json");
            conn.setDoOutput(true);
            conn.setConnectTimeout(CONNECT_TIMEOUT_MS);
            conn.setReadTimeout(READ_TIMEOUT_MS);

            try (OutputStream os = conn.getOutputStream()) {
                byte[] input = jsonPayload.getBytes(StandardCharsets.UTF_8);
                os.write(input, 0, input.length);
            }

            int responseCode = conn.getResponseCode();
            return (responseCode == HttpURLConnection.HTTP_OK
                    || responseCode == HttpURLConnection.HTTP_CREATED);

        } catch (Exception e) {
            System.err.println("[Sync] Network error while pushing payload: " + e.getMessage());
            return false;
        }
    }

    public boolean pullServerChanges() {
        String since = dbManager.getLastSyncTimestamp();
        if (since == null) {
            since = "1970-01-01T00:00:00";
        }

        String responseBody = fetchServerChanges(since);
        if (responseBody == null) {
            return false;
        }

        try {
            mergeIntoSQLite(responseBody);
            return true;
        } catch (Exception e) {
            System.err.println("[Sync] Error merging server response: " + e.getMessage());
            return false;
        }
    }

    private String fetchServerChanges(String since) {
        try {
            String encodedSince = java.net.URLEncoder.encode(since, StandardCharsets.UTF_8);
            URL url = new URL(BASE_URL + PULL_PATH + "?since=" + encodedSince);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();

            conn.setRequestMethod("GET");
            conn.setRequestProperty("Accept", "application/json");
            conn.setConnectTimeout(CONNECT_TIMEOUT_MS);
            conn.setReadTimeout(READ_TIMEOUT_MS);

            int responseCode = conn.getResponseCode();
            if (responseCode != HttpURLConnection.HTTP_OK) {
                return null;
            }

            StringBuilder responseBuilder = new StringBuilder();
            try (BufferedReader reader = new BufferedReader(
                    new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8))) {
                String line;
                while ((line = reader.readLine()) != null) {
                    responseBuilder.append(line);
                }
            }
            return responseBuilder.toString();

        } catch (Exception e) {
            System.err.println("[Sync] Network error while pulling changes: " + e.getMessage());
            return null;
        }
    }

    /** Expected response shape (TODO: confirm against final API contract):
     *  { "topics": [...], "posts": [...], "notifications": [...] }
     *  Each key is optional. */
    private void mergeIntoSQLite(String responseBody) {
        mergeArrayField(responseBody, "topics", this::mergeOneTopic);
        mergeArrayField(responseBody, "posts", this::mergeOnePost);
        mergeArrayField(responseBody, "notifications", this::mergeOneNotification);
    }

    private int mergeArrayField(String json, String fieldName, java.util.function.Consumer<String> mergeOneFn) {
        Pattern arrayPattern = Pattern.compile(
                "\"" + Pattern.quote(fieldName) + "\"\\s*:\\s*\\[(.*?)\\]", Pattern.DOTALL);
        Matcher arrayMatcher = arrayPattern.matcher(json);

        if (!arrayMatcher.find()) {
            return 0;
        }

        String arrayContents = arrayMatcher.group(1).trim();
        if (arrayContents.isEmpty()) {
            return 0;
        }

        Pattern objectPattern = Pattern.compile("\\{(.*?)\\}", Pattern.DOTALL);
        Matcher objectMatcher = objectPattern.matcher(arrayContents);

        int count = 0;
        while (objectMatcher.find()) {
            mergeOneFn.accept(objectMatcher.group(1));
            count++;
        }
        return count;
    }

    private void mergeOneTopic(String objectBody) {
        dbManager.mergeTopic(
                extractInt(objectBody, "TopicID"),
                extractString(objectBody, "Title"),
                extractString(objectBody, "Category"),
                extractInt(objectBody, "CreatedBy"),
                extractString(objectBody, "CreatedAt")
        );
    }

    private void mergeOnePost(String objectBody) {
        dbManager.mergePost(
                extractInt(objectBody, "PostID"),
                extractInt(objectBody, "TopicID"),
                extractInt(objectBody, "UserID"),
                extractString(objectBody, "Content"),
                extractString(objectBody, "CreatedAt")
        );
    }

    private void mergeOneNotification(String objectBody) {
        dbManager.mergeNotification(
                extractInt(objectBody, "NotificationID"),
                extractInt(objectBody, "UserID"),
                extractString(objectBody, "Message"),
                extractBoolean(objectBody, "Status"),
                extractString(objectBody, "CreatedAt"),
                extractString(objectBody, "Type")
        );
    }

    // Lightweight regex-based field extraction — fine for the current flat-object
    // shape; switch to a real JSON library (Jackson/org.json) if responses get
    // more deeply nested than this.

    private String extractString(String json, String key) {
        Matcher m = Pattern.compile("\"" + Pattern.quote(key) + "\"\\s*:\\s*\"(.*?)\"").matcher(json);
        return m.find() ? m.group(1) : "";
    }

    private int extractInt(String json, String key) {
        Matcher m = Pattern.compile("\"" + Pattern.quote(key) + "\"\\s*:\\s*(-?\\d+)").matcher(json);
        return m.find() ? Integer.parseInt(m.group(1)) : -1;
    }

    private boolean extractBoolean(String json, String key) {
        Matcher m = Pattern.compile("\"" + Pattern.quote(key) + "\"\\s*:\\s*(true|false)").matcher(json);
        return m.find() && Boolean.parseBoolean(m.group(1));
    }
}