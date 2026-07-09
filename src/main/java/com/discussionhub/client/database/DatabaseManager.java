package com.discussionhub.client.database;

import com.discussionhub.client.model.SyncQueueItem;
import com.discussionhub.client.model.TopicItem;

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
    public Integer getLoggedInUserSession() {
        String sql = "SELECT UserID FROM DeviceState LIMIT 1;";
        try (Connection conn = DriverManager.getConnection(DB_URL);
             PreparedStatement pstmt = conn.prepareStatement(sql);
             ResultSet rs = pstmt.executeQuery()) {

            if (rs.next()) {
                return rs.getInt("UserID");
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error checking saved session: " + e.getMessage());
        }
        return null;
    }

    private static final String DB_URL = "jdbc:sqlite:discussionhub.db";
    private int currentDeviceId = -1;

    public Connection connect() throws SQLException {
        return DriverManager.getConnection(DB_URL);
    }

    public int getCurrentDeviceId() {
        return currentDeviceId;
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

        try (Connection conn = this.connect();
             Statement stmt = conn.createStatement()) {

            stmt.execute(createUserTable);
            stmt.execute(createDeviceStateTable);
            stmt.execute(createSyncQueueTable);
            stmt.execute(createTopicTable);
            ensureTopicHasGroupIdColumn(stmt);
            stmt.execute(createPostTable);
            stmt.execute(createNotificationTable);

            String insertDefaultUser = "INSERT OR IGNORE INTO User (Username, Password, FullName) VALUES ('student', 'password123', 'Sample Student');";
            stmt.execute(insertDefaultUser);

            System.out.println("[DB] All local tables initialized (User, DeviceState, SyncQueue, Topic, Post, Notification).");

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

    public int verifyUser(String username, String password) {
        String sql = "SELECT UserID FROM User WHERE Username = ? AND Password = ? LIMIT 1;";
        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, username);
            pstmt.setString(2, password);
            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt("UserID");
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error verifying user: " + e.getMessage());
        }
        return -1;
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

    public long insertLocalTopic(String title, String category, int createdByUserId, int groupId) {
        String sql = "INSERT INTO Topic (Title, Category, CreatedBy, CreatedAt, GroupID) VALUES (?, ?, ?, ?, ?);";
        String currentTimestamp = nowAsIsoString();

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            pstmt.setString(1, title);
            pstmt.setString(2, category);
            pstmt.setInt(3, createdByUserId);
            pstmt.setString(4, currentTimestamp);
            pstmt.setInt(5, groupId);

            int affectedRows = pstmt.executeUpdate();

            if (affectedRows > 0) {
                try (ResultSet generatedKeys = pstmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) {
                        return generatedKeys.getLong(1);
                    }
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error saving topic to local SQLite table: " + e.getMessage());
        }
        return -1;
    }

    public List<String> getAllTopicTitles() {
        List<String> titles = new ArrayList<>();
        String sql = "SELECT Title FROM Topic ORDER BY CreatedAt DESC;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql);
             ResultSet rs = pstmt.executeQuery()) {

            while (rs.next()) {
                titles.add(rs.getString("Title"));
            }

        } catch (SQLException e) {
            System.err.println("[DB] Error reading topics: " + e.getMessage());
        }
        return titles;
    }

    public List<TopicItem> getAllTopicsWithDetails() {
        List<TopicItem> topics = new ArrayList<>();
        String sql = "SELECT t.TopicID, t.Title, t.Category, t.CreatedBy, t.CreatedAt, t.GroupID, " +
            "COUNT(p.PostID) AS ReplyCount " +
            "FROM Topic t " +
            "LEFT JOIN Post p ON p.TopicID = t.TopicID " +
            "GROUP BY t.TopicID " +
            "ORDER BY t.CreatedAt DESC;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql);
             ResultSet rs = pstmt.executeQuery()) {

            while (rs.next()) {
                topics.add(new TopicItem(
                    rs.getInt("TopicID"),
                    rs.getString("Title"),
                    rs.getString("Category"),
                    rs.getInt("CreatedBy"),
                    rs.getString("CreatedAt"),
                    rs.getInt("ReplyCount"),
                    rs.getInt("GroupID")
                ));
            }

        } catch (SQLException e) {
            System.err.println("[DB] Error reading topics with details: " + e.getMessage());
        }
        return topics;
    }

    public long insertLocalPost(int topicId, int userId, String content) {
        String sql = "INSERT INTO Post (TopicID, UserID, Content, CreatedAt) VALUES (?, ?, ?, ?);";
        String currentTimestamp = nowAsIsoString();

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            pstmt.setInt(1, topicId);
            pstmt.setInt(2, userId);
            pstmt.setString(3, content);
            pstmt.setString(4, currentTimestamp);

            int affectedRows = pstmt.executeUpdate();

            if (affectedRows > 0) {
                try (ResultSet generatedKeys = pstmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) {
                        return generatedKeys.getLong(1);
                    }
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error saving post to local SQLite table: " + e.getMessage());
        }
        return -1;
    }

    public List<String> getPostsForTopic(int topicId) {
        List<String> contents = new ArrayList<>();
        String sql = "SELECT Content FROM Post WHERE TopicID = ? ORDER BY CreatedAt ASC;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, topicId);
            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    contents.add(rs.getString("Content"));
                }
            }

        } catch (SQLException e) {
            System.err.println("[DB] Error reading posts for topic " + topicId + ": " + e.getMessage());
        }
        return contents;
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

    public List<NotificationItem> getAllNotifications(int userId) {
        List<NotificationItem> notifications = new ArrayList<>();
        String sql = "SELECT * FROM Notification WHERE UserID = ? ORDER BY CreatedAt DESC;";
        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, userId);
            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    notifications.add(new NotificationItem(
                        rs.getInt("NotificationID"),
                        rs.getInt("UserID"),
                        rs.getString("Message"),
                        rs.getInt("Status"),
                        rs.getString("CreatedAt"),
                        rs.getString("Type")
                    ));
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error fetching notifications: " + e.getMessage());
        }
        return notifications;
    }

    public void markAllNotificationsAsRead(int userId) {
        String sql = "UPDATE Notification SET Status = 1 WHERE UserID = ?;";
        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, userId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error marking notifications as read: " + e.getMessage());
        }
    }

    public void handleTopicSubmission(String title, String category, int createdByUserId, int groupId, boolean isOnline) {
        System.out.println("\n[Interceptor] Processing new topic entry...");

        if (isOnline) {
            System.out.println("[Interceptor] Connection detected: ONLINE. Direct routing to server...");
        } else {
            System.out.println("[Interceptor] Connection dropped: OFFLINE! Redirecting to local cache...");

            long localId = insertLocalTopic(title, category, createdByUserId, groupId);

            if (localId != -1) {
                String jsonPayload = String.format(
                    "{\"Title\":\"%s\",\"Category\":\"%s\",\"CreatedBy\":%d,\"GroupID\":%d}",
                    escapeJson(title), escapeJson(category), createdByUserId, groupId
                );

                boolean queued = logToSyncQueue("Topic", localId, "Create", jsonPayload);

                if (queued) {
                    System.out.println("[Interceptor] Success: Topic cached locally and queued for sync.");
                } else {
                    System.err.println("[Interceptor] Failure: SyncQueue write rejected.");
                }
            } else {
                System.err.println("[Interceptor] Failure: Local cache write rejected.");
            }
        }
    }

    public void handlePostSubmission(int topicId, int userId, String content, boolean isOnline) {
        System.out.println("\n[Interceptor] Processing new post entry...");

        if (isOnline) {
            System.out.println("[Interceptor] Connection detected: ONLINE. Direct routing to server...");
        } else {
            System.out.println("[Interceptor] Connection dropped: OFFLINE! Redirecting to local cache...");

            long localId = insertLocalPost(topicId, userId, content);

            if (localId != -1) {
                String jsonPayload = String.format(
                    "{\"TopicID\":%d,\"UserID\":%d,\"Content\":\"%s\"}",
                    topicId, userId, escapeJson(content)
                );

                boolean queued = logToSyncQueue("Post", localId, "Create", jsonPayload);

                if (queued) {
                    System.out.println("[Interceptor] Success: Post cached locally and queued for sync.");
                } else {
                    System.err.println("[Interceptor] Failure: SyncQueue write rejected.");
                }
            } else {
                System.err.println("[Interceptor] Failure: Local cache write rejected.");
            }
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
