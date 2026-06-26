package com.discussionhub.client.database;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;

public class DatabaseManager {

    private static final String DB_URL = "jdbc:sqlite:discussionhub.db";

    public Connection connect() throws SQLException {
        return DriverManager.getConnection(DB_URL);
    }

    public void initializeDatabase() {
        String createDeviceStateTable =
                "CREATE TABLE IF NOT EXISTS DeviceState (" +
                        "    DeviceID TEXT PRIMARY KEY," +
                        "    LastSyncedAt TEXT," +
                        "    IsOnline INTEGER DEFAULT 1" +
                        ");";

        String createSyncQueueTable =
                "CREATE TABLE IF NOT EXISTS SyncQueue (" +
                        "    QueueID INTEGER PRIMARY KEY AUTOINCREMENT," +
                        "    TableName TEXT NOT NULL," +
                        "    RecordID TEXT NOT NULL," +
                        "    ActionType TEXT NOT NULL," +
                        "    CreatedAt TEXT NOT NULL" +
                        ");";

        String createPostTable =
                "CREATE TABLE IF NOT EXISTS Post (" +
                        "    PostID TEXT PRIMARY KEY," +
                        "    Title TEXT NOT NULL," +
                        "    Content TEXT NOT NULL," +
                        "    UserID TEXT NOT NULL," +
                        "    TopicID TEXT NOT NULL," +
                        "    CreatedAt TEXT NOT NULL" +
                        ");";

        String createTopicTable =
                "CREATE TABLE IF NOT EXISTS Topic (" +
                        "    TopicID INTEGER PRIMARY KEY AUTOINCREMENT," +
                        "    Title TEXT NOT NULL," +
                        "    Category TEXT NOT NULL," +
                        "    CreatedBy TEXT NOT NULL," +
                        "    is_resolved INTEGER DEFAULT 0," +
                        "    CreatedAt TEXT NOT NULL" +
                        ");";

        String createNotificationTable =
                "CREATE TABLE IF NOT EXISTS Notification (" +
                        "    NotificationID TEXT PRIMARY KEY," +
                        "    UserID TEXT NOT NULL," +
                        "    Message TEXT NOT NULL," +
                        "    IsRead INTEGER DEFAULT 0," +
                        "    CreatedAt TEXT NOT NULL" +
                        ");";

        try (Connection conn = this.connect();
             Statement stmt = conn.createStatement()) {

            stmt.execute(createDeviceStateTable);
            stmt.execute(createSyncQueueTable);
            stmt.execute(createPostTable);
            stmt.execute(createTopicTable);
            stmt.execute(createNotificationTable);

            System.out.println("Database tables initialized successfully!");

        } catch (SQLException e) {
            System.err.println("Error initializing database tables: " + e.getMessage());
        }
    }


    public long insertLocalTopic(String title, String category, String createdBy) {
        String sql = "INSERT INTO Topic (Title, Category, CreatedBy, is_resolved, CreatedAt) VALUES (?, ?, ?, 0, ?);";
        String currentTimestamp = LocalDateTime.now().format(DateTimeFormatter.ISO_LOCAL_DATE_TIME);

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql, PreparedStatement.RETURN_GENERATED_KEYS)) {

            pstmt.setString(1, title);
            pstmt.setString(2, category);
            pstmt.setString(3, createdBy);
            pstmt.setString(4, currentTimestamp);

            int affectedRows = pstmt.executeUpdate();

            if (affectedRows > 0) {
                try (var generatedKeys = pstmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) {
                        return generatedKeys.getLong(1);
                    }
                }
            }
        } catch (SQLException e) {
            System.err.println("Error saving topic to local SQLite table: " + e.getMessage());
        }
        return -1;
    }

    public boolean logToSyncQueue(String tableName, String recordId, String actionType) {
        String sql = "INSERT INTO SyncQueue (TableName, RecordID, ActionType, CreatedAt) VALUES (?, ?, ?, ?);";
        String currentTimestamp = LocalDateTime.now().format(DateTimeFormatter.ISO_LOCAL_DATE_TIME);

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, tableName);
            pstmt.setString(2, recordId);
            pstmt.setString(3, actionType);
            pstmt.setString(4, currentTimestamp);

            int rows = pstmt.executeUpdate();
            return rows > 0;

        } catch (SQLException e) {
            System.err.println("Error logging action to SyncQueue: " + e.getMessage());
            return false;
        }
    }
    public void handleTopicSubmission(String title, String category, String createdBy, boolean isOnline) {
        System.out.println("\n[Interceptor] Processing new topic entry...");

        if (isOnline) {
            // --- ONLINE ROUTE ---
            System.out.println("[Interceptor] Connection detected: ONLINE.");
            System.out.println("[API Client] Forwarding payload to Laravel API endpoints...");
            // (This is where our upcoming HTTP client code will sit)

        } else {
            System.out.println("[Interceptor] Connection dropped: OFFLINE! Redirecting to local cache...");

            // 1. Write straight to the local SQLite table cache
            long localId = insertLocalTopic(title, category, createdBy);

            if (localId != -1) {
                // 2. Queue the item to tracking so the background thread handles it later
                boolean queued = logToSyncQueue("Topic", String.valueOf(localId), "CREATE");

                if (queued) {
                    System.out.println("[Interceptor] Protection Success: Safely locked inside SyncQueue.");
                } else {
                    System.err.println("[Interceptor] Failure: SyncQueue write rejected.");
                }
            } else {
                System.err.println("[Interceptor] Failure: Local cache write rejected.");
            }
        }
    }
}