package com.discussionhub.client.database;

import com.discussionhub.client.model.SyncQueueItem;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;

public class DatabaseManager {
    private static final String DB_URL = "jdbc:sqlite:discussionhub.db";
    private int currentDeviceId = -1;

    public Connection connect() throws SQLException {
        return DriverManager.getConnection(DB_URL);
    }

    public void initializeDatabase() {

        String createUserTable =
            "CREATE TABLE IF NOT EXISTS User (" +
                "    UserID INTEGER PRIMARY KEY AUTOINCREMENT," +
                "    Username TEXT UNIQUE NOT NULL," +
                "    Password TEXT NOT NULL," +
                "    FullName TEXT" +
                ");";

        String createDeviceStateTable =
            "CREATE TABLE IF NOT EXISTS DeviceState (" +
                "    DeviceID INTEGER PRIMARY KEY AUTOINCREMENT," +
                "    UserID INTEGER NOT NULL," +
                "    LastSyncAt TEXT NOT NULL," +
                "    SyncStatus TEXT NOT NULL" +
                ");";

        String createSyncQueueTable =
            "CREATE TABLE IF NOT EXISTS SyncQueue (" +
                "    SyncQueueID INTEGER PRIMARY KEY AUTOINCREMENT," +
                "    DeviceID INTEGER NOT NULL," +
                "    EntityType TEXT NOT NULL," +
                "    EntityID INTEGER NOT NULL," +
                "    Operation TEXT NOT NULL," +
                "    Payload TEXT NOT NULL," +
                "    IsDirty INTEGER NOT NULL," +
                "    CreatedAt TEXT NOT NULL," +
                "    FOREIGN KEY (DeviceID) REFERENCES DeviceState(DeviceID)" +
                ");";

        String createTopicTable =
            "CREATE TABLE IF NOT EXISTS Topic (" +
                "    TopicID INTEGER PRIMARY KEY AUTOINCREMENT," +
                "    Title TEXT NOT NULL," +
                "    Category TEXT NOT NULL," +
                "    CreatedBy INTEGER NOT NULL," +
                "    CreatedAt TEXT NOT NULL" +
                ");";

        String createPostTable =
            "CREATE TABLE IF NOT EXISTS Post (" +
                "    PostID INTEGER PRIMARY KEY AUTOINCREMENT," +
                "    TopicID INTEGER NOT NULL," +
                "    UserID INTEGER NOT NULL," +
                "    Content TEXT NOT NULL," +
                "    CreatedAt TEXT NOT NULL," +
                "    FOREIGN KEY (TopicID) REFERENCES Topic(TopicID)" +
                ");";

        String createNotificationTable =
            "CREATE TABLE IF NOT EXISTS Notification (" +
                "    NotificationID INTEGER PRIMARY KEY," +
                "    UserID INTEGER NOT NULL," +
                "    Message TEXT NOT NULL," +
                "    Status INTEGER NOT NULL," +
                "    CreatedAt TEXT NOT NULL," +
                "    Type TEXT NOT NULL" +
                ");";

        // Local cache of Group Chat messages, keyed by ConversationID. Lets
        // an offline desktop session show "saved information" instead of a
        // hard error, and lets a message composed while offline be queued
        // (IsPending=1) and shown immediately rather than lost, until the
        // SyncQueue flush actually sends it.
        String createMessageTable =
            "CREATE TABLE IF NOT EXISTS Message (" +
                "    MessageID INTEGER PRIMARY KEY AUTOINCREMENT," +
                "    ConversationID INTEGER NOT NULL," +
                "    UserID INTEGER NOT NULL," +
                "    AuthorName TEXT," +
                "    Body TEXT NOT NULL," +
                "    CreatedAt TEXT NOT NULL," +
                "    IsPending INTEGER NOT NULL DEFAULT 0" +
                ");";

        try (Connection conn = this.connect();
             Statement stmt = conn.createStatement()) {

            stmt.execute(createUserTable);
            stmt.execute(createDeviceStateTable);
            stmt.execute(createSyncQueueTable);
            stmt.execute(createTopicTable);
            ensureTopicHasGroupIdColumn(stmt);
            stmt.execute(createPostTable);
            stmt.execute(createNotificationTable);
            stmt.execute(createMessageTable);

            String insertDefaultUser = "INSERT OR IGNORE INTO User (Username, Password, FullName) VALUES ('student', 'password123', 'Sample Student');";
            stmt.execute(insertDefaultUser);

            System.out.println("[DB] All local tables initialized (User, DeviceState, SyncQueue, Topic, Post, Notification, Message).");

        } catch (SQLException e) {
            System.err.println("[DB] Error initializing database tables: " + e.getMessage());
        }
    }

    private void ensureTopicHasGroupIdColumn(Statement stmt) throws SQLException {
        boolean hasGroupId = false;

        try (ResultSet rs = stmt.executeQuery("PRAGMA table_info(Topic);")) {
            while (rs.next()) {
                if ("GroupID".equalsIgnoreCase(rs.getString("name"))) {
                    hasGroupId = true;
                    break;
                }
            }
        }

        if (!hasGroupId) {
            stmt.execute("ALTER TABLE Topic ADD COLUMN GroupID INTEGER;");
            System.out.println("[DB] Added missing GroupID column to local Topic table.");
        }
    }

    public int ensureDeviceState(int ownerUserId) {
        String selectSql = "SELECT DeviceID FROM DeviceState WHERE UserID = ? LIMIT 1;";
        String insertSql = "INSERT INTO DeviceState (UserID, LastSyncAt, SyncStatus) VALUES (?, ?, ?);";
        String currentTimestamp = nowAsIsoString();

        try (Connection conn = this.connect()) {

            try (PreparedStatement selectStmt = conn.prepareStatement(selectSql)) {
                selectStmt.setInt(1, ownerUserId);
                try (ResultSet rs = selectStmt.executeQuery()) {
                    if (rs.next()) {
                        int existingId = rs.getInt("DeviceID");
                        this.currentDeviceId = existingId;
                        return existingId;
                    }
                }
            }

            try (PreparedStatement insertStmt = conn.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
                insertStmt.setInt(1, ownerUserId);
                insertStmt.setString(2, currentTimestamp);
                insertStmt.setString(3, "Offline");
                insertStmt.executeUpdate();

                try (ResultSet generatedKeys = insertStmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) {
                        int newId = (int) generatedKeys.getLong(1);
                        this.currentDeviceId = newId;
                        return newId;
                    }
                }
            }

        } catch (SQLException e) {
            System.err.println("[DB] Error ensuring DeviceState row: " + e.getMessage());
        }
        return -1;
    }

    public void updateDeviceSyncStatus(String syncStatus) {
        if (currentDeviceId == -1) {
            System.err.println("[DB] Cannot update sync status — no DeviceID set yet. Call ensureDeviceState() first.");
            return;
        }

        String sql = "UPDATE DeviceState SET SyncStatus = ?, LastSyncAt = ? WHERE DeviceID = ?;";
        String currentTimestamp = nowAsIsoString();

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, syncStatus);
            pstmt.setString(2, currentTimestamp);
            pstmt.setInt(3, currentDeviceId);
            pstmt.executeUpdate();

        } catch (SQLException e) {
            System.err.println("[DB] Error updating DeviceState sync status: " + e.getMessage());
        }
    }

    public String getLastSyncTimestamp() {
        if (currentDeviceId == -1) {
            return null;
        }

        String sql = "SELECT LastSyncAt FROM DeviceState WHERE DeviceID = ?;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, currentDeviceId);
            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getString("LastSyncAt");
                }
            }

        } catch (SQLException e) {
            System.err.println("[DB] Error reading last sync timestamp: " + e.getMessage());
        }
        return null;
    }

    public boolean logToSyncQueue(String entityType, long entityId, String operation, String payload) {
        if (currentDeviceId == -1) {
            System.err.println("[DB] Cannot log to SyncQueue — no DeviceID set. Call ensureDeviceState() at startup first.");
            return false;
        }

        String sql = "INSERT INTO SyncQueue (DeviceID, EntityType, EntityID, Operation, Payload, IsDirty, CreatedAt) " +
            "VALUES (?, ?, ?, ?, ?, 1, ?);";
        String currentTimestamp = nowAsIsoString();

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, currentDeviceId);
            pstmt.setString(2, entityType);
            pstmt.setLong(3, entityId);
            pstmt.setString(4, operation);
            pstmt.setString(5, payload);
            pstmt.setString(6, currentTimestamp);

            int rows = pstmt.executeUpdate();
            return rows > 0;

        } catch (SQLException e) {
            System.err.println("[DB] Error logging action to SyncQueue: " + e.getMessage());
            return false;
        }
    }

    public List<SyncQueueItem> getPendingChanges() {
        List<SyncQueueItem> pending = new ArrayList<>();
        String sql = "SELECT SyncQueueID, DeviceID, EntityType, EntityID, Operation, Payload " +
            "FROM SyncQueue WHERE IsDirty = 1 ORDER BY CreatedAt ASC;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql);
             ResultSet rs = pstmt.executeQuery()) {

            while (rs.next()) {
                pending.add(new SyncQueueItem(
                    rs.getInt("SyncQueueID"),
                    rs.getInt("DeviceID"),
                    rs.getString("EntityType"),
                    rs.getLong("EntityID"),
                    rs.getString("Operation"),
                    rs.getString("Payload")
                ));
            }

        } catch (SQLException e) {
            System.err.println("[DB] Error reading pending SyncQueue changes: " + e.getMessage());
        }
        return pending;
    }

    public boolean markSyncQueueItemAsSynced(int syncQueueId) {
        String sql = "UPDATE SyncQueue SET IsDirty = 0 WHERE SyncQueueID = ?;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, syncQueueId);
            return pstmt.executeUpdate() > 0;

        } catch (SQLException e) {
            System.err.println("[DB] Error marking SyncQueue item " + syncQueueId + " as synced: " + e.getMessage());
            return false;
        }
    }

    public void mergeTopic(int topicId, String title, String category, int createdBy, String createdAt, int groupId) {
        String sql = "INSERT OR REPLACE INTO Topic (TopicID, Title, Category, CreatedBy, CreatedAt, GroupID) " +
            "VALUES (?, ?, ?, ?, ?, ?);";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, topicId);
            pstmt.setString(2, title);
            pstmt.setString(3, category);
            pstmt.setInt(4, createdBy);
            pstmt.setString(5, createdAt);
            pstmt.setInt(6, groupId);
            pstmt.executeUpdate();

        } catch (SQLException e) {
            System.err.println("[DB] Error merging Topic " + topicId + " from server: " + e.getMessage());
        }
    }
    public void mergePost(int postId, int topicId, int userId, String content, String createdAt) {
        String sql = "INSERT OR REPLACE INTO Post (PostID, TopicID, UserID, Content, CreatedAt) " +
            "VALUES (?, ?, ?, ?, ?);";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, postId);
            pstmt.setInt(2, topicId);
            pstmt.setInt(3, userId);
            pstmt.setString(4, content);
            pstmt.setString(5, createdAt);
            pstmt.executeUpdate();

        } catch (SQLException e) {
            System.err.println("[DB] Error merging Post " + postId + " from server: " + e.getMessage());
        }
    }

    public void mergeNotification(int notificationId, int userId, String message,
                                  boolean isRead, String createdAt, String type) {
        String sql = "INSERT OR REPLACE INTO Notification (NotificationID, UserID, Message, Status, CreatedAt, Type) " +
            "VALUES (?, ?, ?, ?, ?, ?);";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, notificationId);
            pstmt.setInt(2, userId);
            pstmt.setString(3, message);
            pstmt.setInt(4, isRead ? 1 : 0);
            pstmt.setString(5, createdAt);
            pstmt.setString(6, type);
            pstmt.executeUpdate();

        } catch (SQLException e) {
            System.err.println("[DB] Error merging Notification " + notificationId + " from server: " + e.getMessage());
        }
    }

    // Replaces the cached copy of a conversation's messages with what the
    // server just returned. Never touches IsPending=1 rows (messages queued
    // offline that haven't round-tripped through the server yet) so a
    // refresh can't wipe out something still waiting to sync.
    public void cacheMessages(int conversationId, List<ChatMessageItem> messages) {
        String deleteSql = "DELETE FROM Message WHERE ConversationID = ? AND IsPending = 0;";
        String insertSql = "INSERT INTO Message (ConversationID, UserID, AuthorName, Body, CreatedAt, IsPending) " +
            "VALUES (?, ?, ?, ?, ?, 0);";

        try (Connection conn = this.connect()) {
            try (PreparedStatement del = conn.prepareStatement(deleteSql)) {
                del.setInt(1, conversationId);
                del.executeUpdate();
            }
            try (PreparedStatement ins = conn.prepareStatement(insertSql)) {
                for (ChatMessageItem m : messages) {
                    ins.setInt(1, conversationId);
                    ins.setInt(2, m.getUserId());
                    ins.setString(3, m.getAuthorName());
                    ins.setString(4, m.getBody());
                    ins.setString(5, m.getCreatedAt());
                    ins.addBatch();
                }
                ins.executeBatch();
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error caching chat messages: " + e.getMessage());
        }
    }

    public List<ChatMessageItem> getCachedMessages(int conversationId) {
        List<ChatMessageItem> messages = new ArrayList<>();
        String sql = "SELECT MessageID, ConversationID, UserID, AuthorName, Body, CreatedAt, IsPending " +
            "FROM Message WHERE ConversationID = ? ORDER BY MessageID ASC;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, conversationId);
            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    messages.add(new ChatMessageItem(
                        rs.getInt("MessageID"), rs.getInt("ConversationID"), rs.getInt("UserID"),
                        rs.getString("AuthorName"), rs.getString("Body"), rs.getString("CreatedAt"),
                        rs.getInt("IsPending") == 1
                    ));
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading cached chat messages: " + e.getMessage());
        }
        return messages;
    }

    // Composed while offline: cached locally so it shows up immediately
    // (marked pending), and logged to the existing SyncQueue so the next
    // successful sync actually sends it - reusing infrastructure that
    // already exists rather than building a second queueing mechanism.
    //
    // localDisplayConversationId is whichever conversation the user was
    // looking at (always known, used to show the pending bubble in the
    // right thread locally). payloadConversationId is only set when
    // replying into an *already-resolved* conversation (a restricted
    // thread the user had open); when composing fresh from the main
    // thread with new exclude checkboxes ticked, it's null and the
    // exclude set travels instead, so the server resolves/creates the
    // restricted conversation on sync exactly as it would for a live POST.
    public long queuePendingMessage(int groupId, int localDisplayConversationId, int userId, String authorName,
                                     String body, Integer payloadConversationId, String excludeIdsJsonArray) {
        String sql = "INSERT INTO Message (ConversationID, UserID, AuthorName, Body, CreatedAt, IsPending) " +
            "VALUES (?, ?, ?, ?, ?, 1);";
        String currentTimestamp = nowAsIsoString();
        long localId = -1;

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            pstmt.setInt(1, localDisplayConversationId);
            pstmt.setInt(2, userId);
            pstmt.setString(3, authorName);
            pstmt.setString(4, body);
            pstmt.setString(5, currentTimestamp);

            int affectedRows = pstmt.executeUpdate();
            if (affectedRows > 0) {
                try (ResultSet generatedKeys = pstmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) {
                        localId = generatedKeys.getLong(1);
                    }
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error queueing offline chat message: " + e.getMessage());
            return -1;
        }

        if (localId != -1) {
            String jsonPayload = String.format(
                "{\"GroupID\":%d,\"ConversationID\":%s,\"Body\":\"%s\",\"Exclude\":%s}",
                groupId,
                payloadConversationId != null ? String.valueOf(payloadConversationId) : "null",
                escapeJson(body),
                (excludeIdsJsonArray == null || excludeIdsJsonArray.isBlank()) ? "[]" : excludeIdsJsonArray
            );
            logToSyncQueue("Message", localId, "Create", jsonPayload);
        }
        return localId;
    }

    public void markMessageSynced(int localMessageId) {
        String sql = "UPDATE Message SET IsPending = 0 WHERE MessageID = ?;";
        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, localMessageId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error marking Message " + localMessageId + " as synced: " + e.getMessage());
        }
    }


    private String nowAsIsoString() {
        return LocalDateTime.now().format(DateTimeFormatter.ISO_LOCAL_DATE_TIME);
    }

    private String escapeJson(String input) {
        if (input == null) {
            return "";
        }
        return input.replace("\\", "\\\\").replace("\"", "\\\"");
    }
}
