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

/**
 * DatabaseManager
 * ----------------
 * Owns the local SQLite cache for the desktop client and every read/write
 * operation against it.
 *
 * Covers Week 1 (Day 1-5) and Week 2 (Day 1-5) of Person 4's plan:
 *   - Week 1 Day 1-2: offline-readable subset of tables (Post, Topic, Notification)
 *   - Week 1 Day 3-5: SyncQueue + DeviceState tables, dirty-flag bookkeeping
 *   - Week 2 Day 1-3: this class also exposes the read/merge side that
 *                      DeltaSyncService needs for GET /sync/pull (mergeServerItems)
 *   - Week 2 Day 4-5: simple read methods (getAllTopics, getPostsForTopic) that
 *                      a future GUI screen can call directly, online or offline
 *
 * Every table/column name below is copied verbatim from the Data Dictionary
 * (SDD Section 4.3) — see the comment above each CREATE TABLE statement for
 * which dictionary entity it corresponds to. Do not rename anything here
 * without raising it with the team first (Working Rules, Section 2.1).
 */
public class DatabaseManager {
    public Integer getLoggedInUserSession() {
        String sql = "SELECT UserID FROM DeviceState LIMIT 1;"; // Simplified for now as we only support one device state
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

    // The DeviceID for *this* desktop installation. Set once at startup via
    // ensureDeviceState() and then reused for every SyncQueue write, since every
    // queued change must say which device queued it.
    private int currentDeviceId = -1;

    // ------------------------------------------------------------------
    // Connection handling
    // ------------------------------------------------------------------

    public Connection connect() throws SQLException {
        return DriverManager.getConnection(DB_URL);
    }

    public int getCurrentDeviceId() {
        return currentDeviceId;
    }

    // ------------------------------------------------------------------
    // Schema setup (Week 1, Day 1-5)
    // ------------------------------------------------------------------

    /**
     * Creates every local table this client needs, if they don't already exist.
     * Safe to call every time the app starts — CREATE TABLE IF NOT EXISTS is a no-op
     * once the tables are already there.
     */
    public void initializeDatabase() {

        // A. User - added for authentication
        String createUserTable =
                "CREATE TABLE IF NOT EXISTS User (" +
                        "    UserID INTEGER PRIMARY KEY AUTOINCREMENT," +
                        "    Username TEXT UNIQUE NOT NULL," +
                        "    Password TEXT NOT NULL," +
                        "    FullName TEXT" +
                        ");";

        // V. DeviceState — one row per desktop installation.
        // Created first because SyncQueue.DeviceID is a foreign key into it.
        String createDeviceStateTable =
                "CREATE TABLE IF NOT EXISTS DeviceState (" +
                        "    DeviceID INTEGER PRIMARY KEY AUTOINCREMENT," +
                        "    UserID INTEGER NOT NULL," +          // owner of this device
                        "    LastSyncAt TEXT NOT NULL," +          // timestamp of last successful sync
                        "    SyncStatus TEXT NOT NULL" +           // 'Online' / 'Offline' / 'Syncing'
                        ");";

        // U. SyncQueue — every offline change waiting to be pushed to the server.
        String createSyncQueueTable =
                "CREATE TABLE IF NOT EXISTS SyncQueue (" +
                        "    SyncQueueID INTEGER PRIMARY KEY AUTOINCREMENT," +
                        "    DeviceID INTEGER NOT NULL," +
                        "    EntityType TEXT NOT NULL," +          // e.g. 'Post', 'Topic'
                        "    EntityID INTEGER NOT NULL," +         // PK of the affected local record
                        "    Operation TEXT NOT NULL," +           // 'Create' / 'Update' / 'Delete'
                        "    Payload TEXT NOT NULL," +             // JSON-encoded change data
                        "    IsDirty INTEGER NOT NULL," +          // 1 = pending sync, 0 = confirmed synced
                        "    CreatedAt TEXT NOT NULL," +
                        "    FOREIGN KEY (DeviceID) REFERENCES DeviceState(DeviceID)" +
                        ");";

        // J. Topic — matches the Data Dictionary exactly: no IsResolved column.
        // (If a feature genuinely needs one, raise it in the group chat first —
        // do not add it silently, since Person 1's Laravel migration must match.)
        String createTopicTable =
                "CREATE TABLE IF NOT EXISTS Topic (" +
                        "    TopicID INTEGER PRIMARY KEY AUTOINCREMENT," +
                        "    Title TEXT NOT NULL," +
                        "    Category TEXT NOT NULL," +
                        "    CreatedBy INTEGER NOT NULL," +        // User.UserID of the creator
                        "    CreatedAt TEXT NOT NULL" +
                        ");";

        // D. Post/Message — PostID, TopicID, UserID, Content, CreatedAt. No Title column.
        String createPostTable =
                "CREATE TABLE IF NOT EXISTS Post (" +
                        "    PostID INTEGER PRIMARY KEY AUTOINCREMENT," +
                        "    TopicID INTEGER NOT NULL," +
                        "    UserID INTEGER NOT NULL," +
                        "    Content TEXT NOT NULL," +
                        "    CreatedAt TEXT NOT NULL," +
                        "    FOREIGN KEY (TopicID) REFERENCES Topic(TopicID)" +
                        ");";

        // B. Notification — included now (Week 1 Day 1-2 names it as one of the three
        // offline-readable tables), but nothing pushes TO this table from the desktop;
        // it only ever gets written by mergeServerItems() when a pull brings new
        // notifications down. Columns copied from the Data Dictionary as-is.
        // NOTE: confirm with the team before relying on this in a demo — no one has
        // built the server side of notification delivery yet, so the *shape* is
        // correct per the dictionary, but it has never been exercised against a
        // real payload from Laravel.
        String createNotificationTable =
                "CREATE TABLE IF NOT EXISTS Notification (" +
                        "    NotificationID INTEGER PRIMARY KEY," +   // mirrors server-assigned ID, not local AUTOINCREMENT
                        "    UserID INTEGER NOT NULL," +
                        "    Message TEXT NOT NULL," +
                        "    Status INTEGER NOT NULL," +              // 0 = Unread, 1 = Read (BOOLEAN in dictionary)
                        "    CreatedAt TEXT NOT NULL," +
                        "    Type TEXT NOT NULL" +                    // 'Warning' / 'Quiz' / 'System alert'
                        ");";

        try (Connection conn = this.connect();
             Statement stmt = conn.createStatement()) {

            stmt.execute(createUserTable);
            stmt.execute(createDeviceStateTable);
            stmt.execute(createSyncQueueTable);
            stmt.execute(createTopicTable);
            stmt.execute(createPostTable);
            stmt.execute(createNotificationTable);

            // Add a default user for testing if none exists
            String insertDefaultUser = "INSERT OR IGNORE INTO User (Username, Password, FullName) VALUES ('student', 'password123', 'Sample Student');";
            stmt.execute(insertDefaultUser);

            System.out.println("[DB] All local tables initialized (User, DeviceState, SyncQueue, Topic, Post, Notification).");

        } catch (SQLException e) {
            System.err.println("[DB] Error initializing database tables: " + e.getMessage());
        }
    }

    // ------------------------------------------------------------------
    // DeviceState helpers (Week 1, Day 3-5)
    // ------------------------------------------------------------------

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

    /**
     * Makes sure this desktop installation has a DeviceState row, creating one if
     * needed, and caches its DeviceID for every later SyncQueue write.
     *
     * Call this once at app startup, right after initializeDatabase(), and before
     * anything tries to write to SyncQueue.
     *
     * @param ownerUserId the UserID of whoever is logged in on this machine.
     * @return the DeviceID for this installation, or -1 if something went wrong.
     */
    public int ensureDeviceState(int ownerUserId) {
        String selectSql = "SELECT DeviceID FROM DeviceState WHERE UserID = ? LIMIT 1;";
        String insertSql = "INSERT INTO DeviceState (UserID, LastSyncAt, SyncStatus) VALUES (?, ?, ?);";
        String currentTimestamp = nowAsIsoString();

        try (Connection conn = this.connect()) {

            // Re-use an existing device row for this user if one already exists,
            // instead of creating a new DeviceID every time the app restarts.
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

            // No row yet for this user — create one, starting as Offline until
            // the first network check runs.
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

    /**
     * Updates this device's SyncStatus and LastSyncAt timestamp.
     * Call this whenever the network state changes, and again right after a
     * sync push/pull cycle finishes.
     */
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

    /**
     * Reads this device's LastSyncAt value back out of DeviceState.
     * DeltaSyncService needs this to build the GET /sync/pull?since=... query string,
     * per the PDL ("LastSyncTime = GetLastSyncTimestamp()").
     *
     * @return the ISO-8601 timestamp string of the last successful sync, or null
     *         if this device has never synced (e.g. brand-new install).
     */
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

    // ------------------------------------------------------------------
    // Topic (create-only while offline)
    // ------------------------------------------------------------------

    /**
     * Inserts a new Topic into the local cache.
     *
     * IMPORTANT — create-only scope rule: this method is the ONLY way Topics get
     * written locally while offline. There is deliberately no updateLocalTopic()
     * or deleteLocalTopic() method. Per the 3-Week Plan (Section 1.1 and Week 3
     * Day 1-2), offline actions are scoped to create-only — editing or deleting a
     * record that may have also changed on the server is the classic two-device
     * merge conflict this project explicitly avoids by not allowing it. If a
     * future requirement needs offline edits, that is a team decision, not a
     * silent addition here.
     */
    public long insertLocalTopic(String title, String category, int createdByUserId) {
        String sql = "INSERT INTO Topic (Title, Category, CreatedBy, CreatedAt) VALUES (?, ?, ?, ?);";
        String currentTimestamp = nowAsIsoString();

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            pstmt.setString(1, title);
            pstmt.setString(2, category);
            pstmt.setInt(3, createdByUserId);
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
            System.err.println("[DB] Error saving topic to local SQLite table: " + e.getMessage());
        }
        return -1;
    }

    /**
     * Reads every locally cached Topic. A future forum-list screen can call this
     * directly — it works the same whether the device is online or offline,
     * since it only ever reads from the local cache.
     */
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

    // ------------------------------------------------------------------
    // Post (create-only while offline)
    // ------------------------------------------------------------------

    /**
     * Inserts a new Post into the local cache.
     * Same create-only rule as insertLocalTopic() above — see that method's
     * comment for why there is no local update/delete for Post either.
     */
    /**
     * Returns every locally cached Topic with its full details including reply count.
     * Used by ForumController to display all five SDD figure 6.7 row fields:
     * title, status badge, author (CreatedBy UserID), reply count, and CreatedAt time.
     *
     * The LEFT JOIN with Post counts replies per topic in one query rather than
     * making a separate count call per row (which would be slow for large lists).
     */
    public List<TopicItem> getAllTopicsWithDetails() {
        List<TopicItem> topics = new ArrayList<>();
        String sql = "SELECT t.TopicID, t.Title, t.Category, t.CreatedBy, t.CreatedAt, " +
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
                        rs.getInt("ReplyCount")
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

    /**
     * Reads every locally cached Post for a given Topic, in chronological order.
     * Intended for a future "topic view" screen (Week 2 Day 4-5 in the plan).
     */
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

    // ------------------------------------------------------------------
    // Generic dirty-flag tracking (Week 1 Day 3-5)
    // ------------------------------------------------------------------

    /**
     * Queues an offline change for later sync. This is the ONE place that writes
     * to SyncQueue — every entity type (Topic, Post, ...) funnels through here so
     * the dirty-flag logic only has to be correct in one spot.
     *
     * Column order matches U.SyncQueue exactly:
     * SyncQueueID, DeviceID, EntityType, EntityID, Operation, Payload, IsDirty, CreatedAt.
     *
     * @param entityType the table the change applies to, e.g. "Post" or "Topic"
     * @param entityId   the local primary key of the affected record
     * @param operation  "Create" / "Update" / "Delete" — in this project's scope,
     *                   offline actions are create-only, so callers should only
     *                   ever pass "Create" here. The column still supports the
     *                   other two values because the Data Dictionary defines them,
     *                   but nothing in this codebase currently produces them.
     * @param payload    JSON-encoded representation of the change
     */
    public boolean logToSyncQueue(String entityType, long entityId, String operation, String payload) {
        if (currentDeviceId == -1) {
            System.err.println("[DB] Cannot log to SyncQueue — no DeviceID set. Call ensureDeviceState() at startup first.");
            return false;
        }

        String sql = "INSERT INTO SyncQueue (DeviceID, EntityType, EntityID, Operation, Payload, IsDirty, CreatedAt) " +
                "VALUES (?, ?, ?, ?, ?, 1, ?);"; // IsDirty = 1 (TRUE) — pending sync, per the dictionary's description
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

    /**
     * Returns every SyncQueue row still marked dirty (IsDirty = 1), i.e. every
     * offline change that hasn't been confirmed synced to the server yet.
     * This is the Java equivalent of the PDL's:
     *   PendingChanges = SELECT * FROM Local_SQLite_Cache WHERE IsDirty = True
     */
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

    /**
     * Marks one SyncQueue row as confirmed-synced (IsDirty = 0).
     * Called by DeltaSyncService immediately after the server returns HTTP 200/201
     * for that record's push — matches the PDL's:
     *   UPDATE Local_SQLite_Cache SET IsDirty = False WHERE RecordID = record.RecordID
     */
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

    // ------------------------------------------------------------------
    // Merge incoming server data (Week 2, Day 1-3 — GET /sync/pull side)
    // ------------------------------------------------------------------

    /**
     * Inserts or replaces a Topic row pulled from the server during GET /sync/pull.
     *
     * Uses INSERT OR REPLACE rather than plain INSERT because a pulled Topic
     * might already exist locally (e.g. it was created on another device and
     * this is just an update sweep). Because offline actions are scoped to
     * create-only, a genuine local-vs-server conflict on the SAME record
     * shouldn't occur in this project's scope — but a topic created on a
     * different desktop, or via the web client, absolutely can already exist
     * locally by the time it's pulled, so REPLACE is what keeps this idempotent.
     *
     * @param topicId  the server-assigned TopicID (used as the local PK too,
     *                 so local and server IDs always agree for pulled records)
     */
    public void mergeTopic(int topicId, String title, String category, int createdBy, String createdAt) {
        String sql = "INSERT OR REPLACE INTO Topic (TopicID, Title, Category, CreatedBy, CreatedAt) " +
                "VALUES (?, ?, ?, ?, ?);";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, topicId);
            pstmt.setString(2, title);
            pstmt.setString(3, category);
            pstmt.setInt(4, createdBy);
            pstmt.setString(5, createdAt);
            pstmt.executeUpdate();

        } catch (SQLException e) {
            System.err.println("[DB] Error merging Topic " + topicId + " from server: " + e.getMessage());
        }
    }

    /** Same idea as mergeTopic(), for Post rows arriving via GET /sync/pull. */
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

    /**
     * Same idea, for Notification rows. Status arrives from the server as a
     * boolean-ish value; isRead converts cleanly to SQLite's 0/1 INTEGER storage.
     */
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

    // ------------------------------------------------------------------
    // Submission entry point used by the GUI layer
    // ------------------------------------------------------------------

    /**
     * Handles a new Topic submission coming from the GUI.
     *
     * - If online: hands off for direct server routing (the actual HTTP call for
     *   "create while online" belongs to whichever controller talks to Laravel
     *   directly — this method only handles the OFFLINE branch's local caching,
     *   since that's this module's responsibility).
     * - If offline: writes the Topic locally, then queues it in SyncQueue so
     *   DeltaSyncService can push it once connectivity returns.
     *
     * createdByUserId is an actual User.UserID (INT), matching the FK-shaped
     * column in the Data Dictionary — never a free-text display name.
     */
    public void handleTopicSubmission(String title, String category, int createdByUserId, boolean isOnline) {
        System.out.println("\n[Interceptor] Processing new topic entry...");

        if (isOnline) {
            System.out.println("[Interceptor] Connection detected: ONLINE. Direct routing to server...");
            // Direct online submission is handled elsewhere (Person 1's ForumController
            // equivalent on the Laravel side) — this module only owns the offline path.
        } else {
            System.out.println("[Interceptor] Connection dropped: OFFLINE! Redirecting to local cache...");

            long localId = insertLocalTopic(title, category, createdByUserId);

            if (localId != -1) {
                // Payload keys use the same PascalCase names as the Topic entity's own
                // columns, since EntityType "Topic" tells the consumer which shape to expect.
                String jsonPayload = String.format(
                        "{\"Title\":\"%s\",\"Category\":\"%s\",\"CreatedBy\":%d}",
                        escapeJson(title), escapeJson(category), createdByUserId
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

    /**
     * Same pattern as handleTopicSubmission(), for a new Post / reply.
     * Added for Week 2 completeness — Topic alone isn't the only thing users
     * create offline; replying to a thread is just as common.
     */
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

    // ------------------------------------------------------------------
    // Small shared helpers
    // ------------------------------------------------------------------

    private String nowAsIsoString() {
        return LocalDateTime.now().format(DateTimeFormatter.ISO_LOCAL_DATE_TIME);
    }

    /**
     * Minimal escaping so a quote or backslash inside user-typed content doesn't
     * break the hand-built JSON strings above. This is intentionally simple —
     * if payload construction grows more complex, switch to a real JSON library
     * (e.g. org.json or Jackson) instead of adding more manual escaping here.
     */
    private String escapeJson(String input) {
        if (input == null) {
            return "";
        }
        return input.replace("\\", "\\\\").replace("\"", "\\\"");
    }
}