package com.discussionhub.client.database;

import com.discussionhub.client.model.SyncQueueItem;

import java.io.File;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;

public class DatabaseManager {
    // A relative path here meant the actual .db file's location depended on
    // whatever folder the app happened to be launched from - dev mode
    // (mvn javafx:run, run from the project folder) and the installed app
    // (launched from wherever jpackage put it) each silently got their own
    // separate, empty database, so a "Remember Me" session (or any other
    // locally cached offline data) saved under one never showed up under the
    // other. Anchoring to the OS's per-user app-data folder makes this one
    // fixed location regardless of how the app is launched.
    private static final String DB_URL = "jdbc:sqlite:" + resolveDbFilePath();
    private int currentDeviceId = -1;

    private static String resolveDbFilePath() {
        String base = System.getenv("LOCALAPPDATA");
        if (base == null || base.isBlank()) {
            base = System.getProperty("user.home");
        }
        File dir = new File(base, "DiscussionHub");
        dir.mkdirs();
        return new File(dir, "discussionhub.db").getAbsolutePath();
    }

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

        // Resolves which numeric conversation a group's main (unrestricted)
        // chat thread actually is - GroupChatController only ever learns
        // this from a successful live /chat-messages response and holds it
        // as a plain instance field, which a fresh screen instance (every
        // navigation constructs a new GroupChatController) has no way to
        // recover. Opening Group Chat for a group for the first time in a
        // session while ALREADY offline meant the cache existed but was
        // unreachable, since there was no id to look it up by - this
        // persists that id across cold starts specifically so that lookup
        // still works.
        String createGroupChatStateTable =
            "CREATE TABLE IF NOT EXISTS GroupChatState (" +
                "    GroupID INTEGER PRIMARY KEY," +
                "    MainConversationID INTEGER NOT NULL" +
                ");";

        // Single-row table (Id is always 1) holding the "Remember Me" session,
        // so the app can skip straight to the Dashboard on a cold start
        // instead of always forcing the login screen - the WhatsApp-style
        // behavior the SDD's offline requirement calls for. Only written when
        // the user actually checks Remember Me at login; cleared on logout.
        String createSavedSessionTable =
            "CREATE TABLE IF NOT EXISTS SavedSession (" +
                "    Id INTEGER PRIMARY KEY CHECK (Id = 1)," +
                "    Token TEXT NOT NULL," +
                "    UserID INTEGER NOT NULL," +
                "    UserEmail TEXT," +
                "    FullName TEXT," +
                "    Role TEXT," +
                "    ThemeColor TEXT" +
                ");";

        String createReplyTable =
            "CREATE TABLE IF NOT EXISTS Reply (" +
                "    ReplyID INTEGER PRIMARY KEY AUTOINCREMENT," +
                "    PostID INTEGER NOT NULL," +
                "    UserID INTEGER NOT NULL," +
                "    ReplyContent TEXT NOT NULL," +
                "    CreatedAt TEXT NOT NULL," +
                "    IsPending INTEGER NOT NULL DEFAULT 0," +
                "    FOREIGN KEY (PostID) REFERENCES Post(PostID)" +
                ");";

        // Generic "last known good response" cache, keyed by the exact API
        // path that produced it. Dashboard, Groups Browse, Marks, and
        // Notifications each have their own bespoke JSON shape and their
        // own render(json) method that already knows how to draw it from a
        // live response - rather than building a second bespoke SQLite
        // table + cache-only render path for every one of those screens
        // (the way Topic/Post/Reply needed, since those needed real local
        // querying and offline write-queueing), this just remembers the
        // raw response body itself and replays it through that SAME
        // render(json) method when offline. Simpler, and the offline view
        // is by construction pixel-identical to whatever was last actually
        // seen online, since it's literally the same JSON.
        String createApiCacheTable =
            "CREATE TABLE IF NOT EXISTS ApiCache (" +
                "    Endpoint TEXT PRIMARY KEY," +
                "    ResponseJson TEXT NOT NULL," +
                "    CachedAt TEXT NOT NULL" +
                ");";

        // Tracks "furthest reply/message id this user has actually opened
        // this thread and seen" per Topic/Conversation - entirely local,
        // never synced to the server, since it's purely a per-device read
        // marker (same idea as WhatsApp's local read state). EntityType is
        // "Topic" or "Conversation"; EntityID is that TopicID/ConversationID.
        // Powers both the unread badge on topic/thread list rows and the
        // "NEW MESSAGES" divider inside an open thread.
        String createReadStateTable =
            "CREATE TABLE IF NOT EXISTS ReadState (" +
                "    EntityType TEXT NOT NULL," +
                "    EntityID INTEGER NOT NULL," +
                "    LastReadItemId INTEGER NOT NULL DEFAULT 0," +
                "    PRIMARY KEY (EntityType, EntityID)" +
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
            ensureColumn(stmt, "Topic", "IsPending", "INTEGER NOT NULL DEFAULT 0");
            stmt.execute(createPostTable);
            ensureColumn(stmt, "Post", "IsPending", "INTEGER NOT NULL DEFAULT 0");
            boolean postAuthorNameJustAdded = ensureColumn(stmt, "Post", "AuthorName", "TEXT");
            stmt.execute(createReplyTable);
            boolean replyAuthorNameJustAdded = ensureColumn(stmt, "Reply", "AuthorName", "TEXT");
            boolean replyAttachmentUrlJustAdded = ensureColumn(stmt, "Reply", "AttachmentUrl", "TEXT");
            ensureColumn(stmt, "Reply", "AttachmentType", "TEXT");
            ensureColumn(stmt, "Reply", "AttachmentName", "TEXT");
            stmt.execute(createNotificationTable);
            stmt.execute(createMessageTable);
            stmt.execute(createGroupChatStateTable);
            stmt.execute(createSavedSessionTable);
            stmt.execute(createApiCacheTable);
            stmt.execute(createReadStateTable);

            if (postAuthorNameJustAdded || replyAuthorNameJustAdded || replyAttachmentUrlJustAdded) {
                ensureFullResyncAfterSchemaUpgrade(stmt);
            }

            String insertDefaultUser = "INSERT OR IGNORE INTO User (Username, Password, FullName) VALUES ('student', 'password123', 'Sample Student');";
            stmt.execute(insertDefaultUser);

            System.out.println("[DB] All local tables initialized (User, DeviceState, SyncQueue, Topic, Post, Reply, Notification, Message, GroupChatState).");

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

    /** Generic column-add-if-missing, for tables that predate a new column
     *  on existing local installs (SQLite has no "ADD COLUMN IF NOT EXISTS").
     *  Returns true iff the column was actually just added (didn't already
     *  exist) - callers use that to detect "this is an existing install
     *  that predates this column" and react accordingly (see
     *  ensureFullResyncAfterSchemaUpgrade below). */
    private boolean ensureColumn(Statement stmt, String table, String column, String columnDefSql) throws SQLException {
        boolean hasColumn = false;
        try (ResultSet rs = stmt.executeQuery("PRAGMA table_info(" + table + ");")) {
            while (rs.next()) {
                if (column.equalsIgnoreCase(rs.getString("name"))) {
                    hasColumn = true;
                    break;
                }
            }
        }
        if (!hasColumn) {
            stmt.execute("ALTER TABLE " + table + " ADD COLUMN " + column + " " + columnDefSql + ";");
            System.out.println("[DB] Added missing " + column + " column to local " + table + " table.");
            return true;
        }
        return false;
    }

    /** Delta sync only ever asks the server "what changed since my last
     *  sync" - it was never designed to re-fetch content that hasn't
     *  changed just because a NEW field (like AuthorName) was added to what
     *  gets cached for it. Without this, an existing install's Post/Reply
     *  rows synced before that column existed would show a blank author
     *  forever, since nothing about those rows ever "changes" again to
     *  trigger re-fetching them. Rewinding every device's watermark to
     *  epoch the one time this column gets added forces exactly one full
     *  re-pull, transparently, the next time each device syncs - not a
     *  manual fix a user would ever need to know to run. */
    private void ensureFullResyncAfterSchemaUpgrade(Statement stmt) throws SQLException {
        stmt.execute("UPDATE DeviceState SET LastSyncAt = '1970-01-01T00:00:00';");
        System.out.println("[DB] Post/Reply AuthorName/Attachment column just added - rewound every device's sync watermark to force one full backfill re-pull.");
    }

    public int ensureDeviceState(int ownerUserId) {
        String selectSql = "SELECT DeviceID FROM DeviceState WHERE UserID = ? LIMIT 1;";
        String insertSql = "INSERT INTO DeviceState (UserID, LastSyncAt, SyncStatus) VALUES (?, ?, ?);";

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
                // A brand-new device's very first pull needs everything
                // that already exists server-side, not just changes from
                // this exact moment onward - DeltaSyncService.pullServerChanges()
                // uses LastSyncAt as its "since" filter, so seeding it with
                // "now" here meant a fresh install/account silently never
                // cached any pre-existing topic/post/reply history at all
                // (every WHERE CreatedAt > since matched nothing until
                // something new was posted after this row was created).
                insertStmt.setString(2, "1970-01-01T00:00:00");
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

    /** Status only - does NOT touch LastSyncAt. DeltaSyncService calls this
     *  with "Syncing" right before pullServerChanges() reads LastSyncAt as
     *  its "since" filter - if this updated LastSyncAt too (as it used to),
     *  every single pull would read back the timestamp THIS SAME call just
     *  set to "now", making "since" always "now" and every pull a no-op
     *  that silently found nothing, forever, no matter how long it had
     *  actually been since the last real sync. */
    public void updateDeviceSyncStatus(String syncStatus) {
        if (currentDeviceId == -1) {
            System.err.println("[DB] Cannot update sync status — no DeviceID set yet. Call ensureDeviceState() first.");
            return;
        }

        String sql = "UPDATE DeviceState SET SyncStatus = ? WHERE DeviceID = ?;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, syncStatus);
            pstmt.setInt(2, currentDeviceId);
            pstmt.executeUpdate();

        } catch (SQLException e) {
            System.err.println("[DB] Error updating DeviceState sync status: " + e.getMessage());
        }
    }

    /** Advances the "since" watermark to now - call only after a pull has
     *  actually succeeded, never just because a sync cycle started. */
    public void advanceLastSyncTimestamp() {
        if (currentDeviceId == -1) return;

        String sql = "UPDATE DeviceState SET LastSyncAt = ? WHERE DeviceID = ?;";
        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, nowAsIsoString());
            pstmt.setInt(2, currentDeviceId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error advancing last sync timestamp: " + e.getMessage());
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
    public void mergePost(int postId, int topicId, int userId, String content, String createdAt, String authorName) {
        String sql = "INSERT OR REPLACE INTO Post (PostID, TopicID, UserID, Content, CreatedAt, AuthorName) " +
            "VALUES (?, ?, ?, ?, ?, ?);";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, postId);
            pstmt.setInt(2, topicId);
            pstmt.setInt(3, userId);
            pstmt.setString(4, content);
            pstmt.setString(5, createdAt);
            pstmt.setString(6, authorName);
            pstmt.executeUpdate();

        } catch (SQLException e) {
            System.err.println("[DB] Error merging Post " + postId + " from server: " + e.getMessage());
        }
    }

    // ---- Topic/Post offline cache + queue, mirroring the Message pattern
    // above (cacheMessages/getCachedMessages/queuePendingMessage) --------

    public List<CachedTopicItem> getCachedTopics(int groupId) {
        List<CachedTopicItem> topics = new ArrayList<>();
        String sql = "SELECT TopicID, Title, Category, CreatedBy, CreatedAt, IsPending, GroupID " +
            "FROM Topic WHERE GroupID = ? ORDER BY CreatedAt DESC;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, groupId);
            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    topics.add(new CachedTopicItem(
                        rs.getInt("TopicID"), rs.getString("Title"), rs.getString("Category"),
                        rs.getInt("CreatedBy"), rs.getString("CreatedAt"), rs.getInt("IsPending") == 1,
                        rs.getInt("GroupID")
                    ));
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading cached topics: " + e.getMessage());
        }
        return topics;
    }

    /** Single-topic lookup by id - lets TopicController recover its own
     *  Group Info panel (and where "Back" should go) on a cold offline
     *  start, when it was reached without ever calling the live
     *  /api/topics/{id} endpoint that would normally set that context. */
    public CachedTopicItem getCachedTopicById(int topicId) {
        String sql = "SELECT TopicID, Title, Category, CreatedBy, CreatedAt, IsPending, GroupID FROM Topic WHERE TopicID = ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, topicId);
            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return new CachedTopicItem(
                        rs.getInt("TopicID"), rs.getString("Title"), rs.getString("Category"),
                        rs.getInt("CreatedBy"), rs.getString("CreatedAt"), rs.getInt("IsPending") == 1,
                        rs.getInt("GroupID")
                    );
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading cached topic " + topicId + ": " + e.getMessage());
        }
        return null;
    }

    public List<CachedPostItem> getCachedPosts(int topicId) {
        List<CachedPostItem> posts = new ArrayList<>();
        String sql = "SELECT PostID, TopicID, UserID, Content, CreatedAt, IsPending, AuthorName " +
            "FROM Post WHERE TopicID = ? ORDER BY PostID ASC;";

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, topicId);
            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    posts.add(new CachedPostItem(
                        rs.getInt("PostID"), rs.getInt("TopicID"), rs.getInt("UserID"),
                        rs.getString("Content"), rs.getString("CreatedAt"), rs.getInt("IsPending") == 1,
                        rs.getString("AuthorName")
                    ));
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading cached posts: " + e.getMessage());
        }
        return posts;
    }

    /** A new topic composed while offline: cached locally (negative TopicID
     *  so it can never collide with a real server id) and logged to
     *  SyncQueue, mirroring queuePendingMessage exactly. Returns the local
     *  (negative) TopicID, or -1 on failure. */
    public long queuePendingTopic(int groupId, String category, int userId, String title, String content,
                                   String audience, String excludeIdsJsonArray) {
        String currentTimestamp = nowAsIsoString();
        long localId;

        String sql = "SELECT MIN(TopicID) FROM Topic;";
        try (Connection conn = this.connect(); Statement stmt = conn.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {
            long lowest = rs.next() ? rs.getLong(1) : 0;
            localId = Math.min(lowest, 0) - 1;
        } catch (SQLException e) {
            System.err.println("[DB] Error allocating local topic id: " + e.getMessage());
            return -1;
        }

        String insertSql = "INSERT INTO Topic (TopicID, Title, Category, CreatedBy, CreatedAt, GroupID, IsPending) " +
            "VALUES (?, ?, ?, ?, ?, ?, 1);";
        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(insertSql)) {
            pstmt.setLong(1, localId);
            pstmt.setString(2, title);
            pstmt.setString(3, category);
            pstmt.setInt(4, userId);
            pstmt.setString(5, currentTimestamp);
            pstmt.setInt(6, groupId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error queueing offline topic: " + e.getMessage());
            return -1;
        }

        String jsonPayload = String.format(
            "{\"GroupID\":%d,\"Category\":\"%s\",\"CreatedBy\":%d,\"Title\":\"%s\",\"Content\":\"%s\",\"Audience\":\"%s\",\"Exclude\":%s}",
            groupId, escapeJson(category), userId, escapeJson(title), escapeJson(content), escapeJson(audience),
            (excludeIdsJsonArray == null || excludeIdsJsonArray.isBlank()) ? "[]" : excludeIdsJsonArray
        );
        logToSyncQueue("Topic", localId, "Create", jsonPayload);
        return localId;
    }

    /** Topics are individually navigated to by id (unlike chat messages),
     *  so the negative local placeholder queuePendingTopic() assigned has to
     *  be swapped for the server's real TopicID once the push succeeds -
     *  otherwise every cache read after this point would keep pointing at
     *  an id that only ever existed on this device. Also re-points the
     *  topic's own local Post rows (its opening post) at the new id. */
    public void reassignTopicId(long localTopicId, int realTopicId) {
        try (Connection conn = this.connect()) {
            try (PreparedStatement pstmt = conn.prepareStatement(
                    "UPDATE Post SET TopicID = ? WHERE TopicID = ?;")) {
                pstmt.setInt(1, realTopicId);
                pstmt.setLong(2, localTopicId);
                pstmt.executeUpdate();
            }
            try (PreparedStatement pstmt = conn.prepareStatement(
                    "UPDATE Topic SET TopicID = ?, IsPending = 0 WHERE TopicID = ?;")) {
                pstmt.setInt(1, realTopicId);
                pstmt.setLong(2, localTopicId);
                pstmt.executeUpdate();
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reassigning local Topic " + localTopicId + " to server id " + realTopicId + ": " + e.getMessage());
        }
    }

    public void markPostSynced(long localPostId) {
        String sql = "UPDATE Post SET IsPending = 0 WHERE PostID = ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setLong(1, localPostId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error marking Post " + localPostId + " as synced: " + e.getMessage());
        }
    }

    // ---- Reply offline cache + queue (replies to a topic's own Post) ---

    public void mergeReply(int replyId, int postId, int userId, String content, String createdAt, String authorName,
                            String attachmentUrl, String attachmentType, String attachmentName) {
        String sql = "INSERT OR REPLACE INTO Reply (ReplyID, PostID, UserID, ReplyContent, CreatedAt, IsPending, AuthorName, AttachmentUrl, AttachmentType, AttachmentName) " +
            "VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?);";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, replyId);
            pstmt.setInt(2, postId);
            pstmt.setInt(3, userId);
            pstmt.setString(4, content);
            pstmt.setString(5, createdAt);
            pstmt.setString(6, authorName);
            pstmt.setString(7, attachmentUrl);
            pstmt.setString(8, attachmentType);
            pstmt.setString(9, attachmentName);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error merging Reply " + replyId + " from server: " + e.getMessage());
        }
    }

    public List<CachedReplyItem> getCachedReplies(int postId) {
        List<CachedReplyItem> replies = new ArrayList<>();
        String sql = "SELECT ReplyID, PostID, UserID, ReplyContent, CreatedAt, IsPending, AuthorName, AttachmentUrl, AttachmentType, AttachmentName " +
            "FROM Reply WHERE PostID = ? ORDER BY ReplyID ASC;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, postId);
            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    replies.add(new CachedReplyItem(
                        rs.getInt("ReplyID"), rs.getInt("PostID"), rs.getInt("UserID"),
                        rs.getString("ReplyContent"), rs.getString("CreatedAt"), rs.getInt("IsPending") == 1,
                        rs.getString("AuthorName"), rs.getString("AttachmentUrl"),
                        rs.getString("AttachmentType"), rs.getString("AttachmentName")
                    ));
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading cached replies: " + e.getMessage());
        }
        return replies;
    }

    /** A reply composed while offline. Text only - attachments aren't
     *  supported offline (postReplyMultipart() is the only place that
     *  handles those, and only when actually online). authorName is the
     *  CURRENT user's own name - the same "show it immediately, mark it
     *  pending" treatment as an offline chat message. */
    public long queuePendingReply(int postId, int userId, String content, String authorName) {
        String currentTimestamp = nowAsIsoString();
        String insertSql = "INSERT INTO Reply (PostID, UserID, ReplyContent, CreatedAt, IsPending, AuthorName) VALUES (?, ?, ?, ?, 1, ?);";
        long localId = -1;

        try (Connection conn = this.connect();
             PreparedStatement pstmt = conn.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
            pstmt.setInt(1, postId);
            pstmt.setInt(2, userId);
            pstmt.setString(3, content);
            pstmt.setString(4, currentTimestamp);
            pstmt.setString(5, authorName);
            int affectedRows = pstmt.executeUpdate();
            if (affectedRows > 0) {
                try (ResultSet generatedKeys = pstmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) localId = generatedKeys.getLong(1);
                }
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error queueing offline reply: " + e.getMessage());
            return -1;
        }

        if (localId != -1) {
            String jsonPayload = String.format(
                "{\"PostID\":%d,\"UserID\":%d,\"ReplyContent\":\"%s\"}",
                postId, userId, escapeJson(content)
            );
            logToSyncQueue("Reply", localId, "Create", jsonPayload);
        }
        return localId;
    }

    public void markReplySynced(long localReplyId) {
        String sql = "UPDATE Reply SET IsPending = 0 WHERE ReplyID = ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setLong(1, localReplyId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error marking Reply " + localReplyId + " as synced: " + e.getMessage());
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

    /** Remembers the raw response body of a successful GET, keyed by the
     *  exact path (including query string) that produced it - e.g. two
     *  different pages of the same paginated endpoint are cached as two
     *  separate entries, each independently replayable. */
    public void cacheApiResponse(String endpoint, String responseJson) {
        String sql = "INSERT OR REPLACE INTO ApiCache (Endpoint, ResponseJson, CachedAt) VALUES (?, ?, ?);";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, endpoint);
            pstmt.setString(2, responseJson);
            pstmt.setString(3, nowAsIsoString());
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error caching API response for " + endpoint + ": " + e.getMessage());
        }
    }

    /** Returns null if this endpoint was never successfully cached. */
    public String getCachedApiResponse(String endpoint) {
        String sql = "SELECT ResponseJson FROM ApiCache WHERE Endpoint = ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, endpoint);
            try (ResultSet rs = pstmt.executeQuery()) {
                return rs.next() ? rs.getString("ResponseJson") : null;
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading cached API response for " + endpoint + ": " + e.getMessage());
            return null;
        }
    }

    // ---- Local read-state (unread badges + "NEW MESSAGES" divider) -----

    /** 0 means "never visited" - callers that want "show everything as
     *  unread until first visit" (list badges) can use this value as-is;
     *  callers that want "only show a marker once there's a genuine
     *  baseline" (the in-thread divider) should treat 0 as "don't show
     *  anything" instead. */
    public int getLastReadItemId(String entityType, int entityId) {
        String sql = "SELECT LastReadItemId FROM ReadState WHERE EntityType = ? AND EntityID = ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, entityType);
            pstmt.setInt(2, entityId);
            try (ResultSet rs = pstmt.executeQuery()) {
                return rs.next() ? rs.getInt("LastReadItemId") : 0;
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading ReadState for " + entityType + " " + entityId + ": " + e.getMessage());
            return 0;
        }
    }

    /** Never regresses - a stale/smaller latestItemId (e.g. a call that got
     *  reordered relative to another) must not un-mark something already
     *  known to be read. */
    public void markRead(String entityType, int entityId, int latestItemId) {
        String sql = "INSERT INTO ReadState (EntityType, EntityID, LastReadItemId) VALUES (?, ?, ?) " +
            "ON CONFLICT(EntityType, EntityID) DO UPDATE SET LastReadItemId = MAX(LastReadItemId, excluded.LastReadItemId);";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, entityType);
            pstmt.setInt(2, entityId);
            pstmt.setInt(3, latestItemId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error updating ReadState for " + entityType + " " + entityId + ": " + e.getMessage());
        }
    }

    /** Entirely local - the Reply cache already holds every reply across
     *  every topic in the user's groups (delta sync pulls by group
     *  membership, not by "topics this device has opened"), so this works
     *  identically online or offline. A never-visited topic (LastReadItemId
     *  still 0) correctly counts every existing reply as unread. */
    public int countUnreadReplies(int topicId) {
        int lastRead = getLastReadItemId("Topic", topicId);
        String sql = "SELECT COUNT(*) AS cnt FROM Reply r JOIN Post p ON r.PostID = p.PostID " +
            "WHERE p.TopicID = ? AND r.ReplyID > ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, topicId);
            pstmt.setInt(2, lastRead);
            try (ResultSet rs = pstmt.executeQuery()) {
                return rs.next() ? rs.getInt("cnt") : 0;
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error counting unread replies for topic " + topicId + ": " + e.getMessage());
            return 0;
        }
    }

    /** Same idea as countUnreadReplies(), for a Group Chat conversation. */
    public int countUnreadMessages(int conversationId) {
        int lastRead = getLastReadItemId("Conversation", conversationId);
        String sql = "SELECT COUNT(*) AS cnt FROM Message WHERE ConversationID = ? AND MessageID > ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, conversationId);
            pstmt.setInt(2, lastRead);
            try (ResultSet rs = pstmt.executeQuery()) {
                return rs.next() ? rs.getInt("cnt") : 0;
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error counting unread messages for conversation " + conversationId + ": " + e.getMessage());
            return 0;
        }
    }

    public void rememberMainConversationId(int groupId, int mainConversationId) {
        String sql = "INSERT OR REPLACE INTO GroupChatState (GroupID, MainConversationID) VALUES (?, ?);";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, groupId);
            pstmt.setInt(2, mainConversationId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error remembering main conversation id for group " + groupId + ": " + e.getMessage());
        }
    }

    /** Returns 0 if this group's main conversation has never been resolved
     *  on this device (matching GroupChatController's own "0 = unknown"
     *  convention for its in-memory mainConversationId field). */
    public int getRememberedMainConversationId(int groupId) {
        String sql = "SELECT MainConversationID FROM GroupChatState WHERE GroupID = ?;";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, groupId);
            try (ResultSet rs = pstmt.executeQuery()) {
                return rs.next() ? rs.getInt("MainConversationID") : 0;
            }
        } catch (SQLException e) {
            System.err.println("[DB] Error reading remembered main conversation id for group " + groupId + ": " + e.getMessage());
            return 0;
        }
    }

    /** Persists a "Remember Me" login - only called when the user actually
     *  checks that box at login. Id is always 1, so this always overwrites
     *  whatever was saved before (one device only ever remembers one user). */
    public void saveSession(String token, int userId, String userEmail, String fullName, String role, String themeColor) {
        String sql = "INSERT OR REPLACE INTO SavedSession (Id, Token, UserID, UserEmail, FullName, Role, ThemeColor) " +
            "VALUES (1, ?, ?, ?, ?, ?, ?);";
        try (Connection conn = this.connect(); PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, token);
            pstmt.setInt(2, userId);
            pstmt.setString(3, userEmail);
            pstmt.setString(4, fullName);
            pstmt.setString(5, role);
            pstmt.setString(6, themeColor);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("[DB] Error saving session: " + e.getMessage());
        }
    }

    /** Returns null if no session was ever remembered on this device (or it
     *  was cleared by a later logout/un-checking Remember Me). */
    public SavedSession loadSession() {
        String sql = "SELECT Token, UserID, UserEmail, FullName, Role, ThemeColor FROM SavedSession WHERE Id = 1;";
        try (Connection conn = this.connect(); Statement stmt = conn.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {
            if (!rs.next()) return null;
            return new SavedSession(rs.getString("Token"), rs.getInt("UserID"), rs.getString("UserEmail"),
                rs.getString("FullName"), rs.getString("Role"), rs.getString("ThemeColor"));
        } catch (SQLException e) {
            System.err.println("[DB] Error loading saved session: " + e.getMessage());
            return null;
        }
    }

    /** Called on logout, and whenever a login happens with Remember Me
     *  unchecked, so an earlier remembered session on this device doesn't
     *  linger and silently sign the next person back in as someone else. */
    public void clearSession() {
        String sql = "DELETE FROM SavedSession WHERE Id = 1;";
        try (Connection conn = this.connect(); Statement stmt = conn.createStatement()) {
            stmt.execute(sql);
        } catch (SQLException e) {
            System.err.println("[DB] Error clearing saved session: " + e.getMessage());
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


    /** A real UTC instant (e.g. "2026-07-19T16:01:29.797000Z"), matching the
     *  format every server-provided CreatedAt timestamp already uses -
     *  LocalDateTime.now() (the old implementation) has no zone/offset at
     *  all, which Instant.parse() (used by TextUtil.timeAgo() to render
     *  "X ago") rejects outright, falling back to showing that raw,
     *  zone-less string as-is instead of a relative time. */
    private String nowAsIsoString() {
        return java.time.Instant.now().toString();
    }

    private String escapeJson(String input) {
        if (input == null) {
            return "";
        }
        return input.replace("\\", "\\\\").replace("\"", "\\\"");
    }
}
