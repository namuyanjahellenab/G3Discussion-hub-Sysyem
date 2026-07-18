package com.discussionhub.client;

import com.discussionhub.client.database.ChatMessageItem;
import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import com.pusher.client.Pusher;
import com.pusher.client.PusherOptions;
import com.pusher.client.channel.PrivateChannelEventListener;
import com.pusher.client.channel.PusherEvent;
import com.pusher.client.util.HttpChannelAuthorizer;
import javafx.animation.Animation;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.CheckBox;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.TextField;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.util.Duration;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

// The desktop equivalent of the web's Group Chat (student.messages / GroupChatController):
// main group-wide thread + any number of restricted threads (a thread that
// excludes specific group members). Mirrors the web's inline "Exclude
// members" composer panel rather than a modal dialog.
public class GroupChatController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    // Must match REVERB_APP_KEY / REVERB_HOST / REVERB_PORT in the server's
    // .env - this is the same local Reverb server the web client's
    // resources/js/echo.js connects to, just over the raw Pusher-protocol
    // client instead of Laravel Echo.
    private static final String REVERB_APP_KEY = "discussionhublocalkey";
    private static final String REVERB_HOST = "127.0.0.1";
    private static final int REVERB_WS_PORT = 8080;

    @FXML private Label titleLabel;
    @FXML private Label subtitleLabel;
    @FXML private Label syncStatusLabel;
    @FXML private VBox groupSwitcherBox;
    @FXML private VBox threadListBox;
    @FXML private ScrollPane scrollPane;
    @FXML private VBox messagesBox;
    @FXML private TextField messageField;
    @FXML private Button excludeToggleButton;
    @FXML private VBox excludePanel;
    @FXML private FlowPane excludeCheckboxesBox;
    @FXML private Label excludeHintLabel;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private int groupId;
    private int mainConversationId;
    private int activeConversationId;
    private boolean isRestricted;
    private final List<CheckBox> excludeCheckboxes = new ArrayList<>();

    // Local "unread" tracking (see DatabaseManager's ReadState table),
    // mirroring TopicController's - unreadThresholdMessageId is frozen the
    // moment a conversation becomes active (not updated live), so the
    // NEW MESSAGES divider doesn't jump around while it's open; switching
    // to a different thread (or leaving the screen) marks the previous one
    // read using maxSeenMessageId, so it's gone next time it's opened.
    private int lastActiveConversationIdForReadTracking = 0;
    private int unreadThresholdMessageId = 0;
    private int maxSeenMessageId = 0;

    // Messages sent from elsewhere (the web, another device) have no way to
    // reach an already-open desktop chat window - same gap as TopicController -
    // so we poll instead. Polling only refreshes the thread list + messages,
    // never the composer, so an in-progress draft or exclude-checkbox
    // selection is never wiped out from under the user.
    private static final Duration AUTO_REFRESH_INTERVAL = Duration.seconds(10);
    private Timeline autoRefreshTimeline;

    // Primary "instant" update path. The 10s poll above stays as a
    // resilience fallback (covers a dropped socket, and keeps the
    // restricted-threads list fresh) rather than being removed.
    private Pusher pusher;
    private String currentChannelName;

    // Every group the user belongs to, so the thread list can offer a "Your
    // Groups" switcher above the current group's threads - mirrors
    // student.messages.blade.php's @foreach($userGroups as $g) block, which
    // desktop never had (it always opened whichever group the sidebar's
    // Group Chat button happened to pick, with no way to see another one).
    private JSONArray myGroups = new JSONArray();

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("groupchat");
        sidebarController.setOnBeforeNavigate(() -> {
            stopAutoRefresh();
            markCurrentConversationRead();
            if (pusher != null) pusher.disconnect();
        });
        boolean isOnline = NetworkUtil.isNetworkAvailable();
        syncStatusLabel.setText(isOnline ? "● ONLINE" : "● OFFLINE");
        syncStatusLabel.setStyle("-fx-text-fill: " + (isOnline ? "#90ee90" : "#ffcc00") + "; -fx-font-size: 12; -fx-font-weight: bold;");
        connectRealtime();
        loadMyGroups();
    }

    private void loadMyGroups() {
        new Thread(() -> {
            String body = get("/api/dashboard");
            if (body == null) return;
            try {
                JSONArray groups = new JSONObject(body).getJSONArray("joined_groups");
                Platform.runLater(() -> {
                    myGroups = groups;
                    renderGroupSwitcher();
                });
            } catch (Exception e) {
                System.err.println("[GroupChat] Error loading groups: " + e.getMessage());
            }
        }).start();
    }

    // Only shown with more than one group, matching the web's
    // @if($userGroups->count() > 1) - a single-group member has nothing to
    // switch to, so the section would just be noise.
    private void renderGroupSwitcher() {
        groupSwitcherBox.getChildren().clear();
        if (myGroups.length() <= 1) return;

        Label heading = new Label("YOUR GROUPS");
        heading.getStyleClass().add("muted-text");
        heading.setStyle("-fx-font-size: 10.5; -fx-font-weight: bold;");
        VBox.setMargin(heading, new Insets(10, 0, 4, 10));
        groupSwitcherBox.getChildren().add(heading);

        for (int i = 0; i < myGroups.length(); i++) {
            JSONObject group = myGroups.getJSONObject(i);
            int id = group.getInt("id");
            String name = group.getString("name");
            Button item = threadItem("📚  " + name, "Tap to switch groups", id == groupId);
            item.setOnAction(e -> switchGroup(id, name));
            groupSwitcherBox.getChildren().add(item);
        }
    }

    private void switchGroup(int newGroupId, String newGroupName) {
        if (newGroupId == groupId) return;
        setGroupContext(newGroupId, newGroupName);
        renderGroupSwitcher();
    }

    private void connectRealtime() {
        HttpChannelAuthorizer authorizer = new HttpChannelAuthorizer(BASE_URL + "/api/broadcasting/auth");
        Map<String, String> headers = new HashMap<>();
        headers.put("Authorization", "Bearer " + SessionManager.token);
        headers.put("Accept", "application/json");
        authorizer.setHeaders(headers);

        PusherOptions options = new PusherOptions()
            .setHost(REVERB_HOST)
            .setWsPort(REVERB_WS_PORT)
            .setWssPort(REVERB_WS_PORT)
            .setUseTLS(false)
            .setChannelAuthorizer(authorizer);

        pusher = new Pusher(REVERB_APP_KEY, options);
        pusher.connect();
    }

    // Leaves whichever channel was previously subscribed (if any) and joins
    // the one for the conversation now being viewed. A no-op if we're
    // already on the right channel (renderQuiet() re-calls this every poll
    // tick with the same conversation id most of the time).
    private void subscribeRealtime(int conversationId) {
        // Unlike Echo (which prepends "private-" for you), pusher-java-client
        // requires the prefix to already be part of the name passed in.
        // Laravel strips it back off before matching against the
        // 'conversation.{conversationId}' pattern in routes/channels.php.
        String channelName = "private-conversation." + conversationId;
        if (channelName.equals(currentChannelName)) return;

        if (currentChannelName != null) {
            pusher.unsubscribe(currentChannelName);
        }

        currentChannelName = channelName;
        pusher.subscribePrivate(channelName, new PrivateChannelEventListener() {
            @Override
            public void onAuthenticationFailure(String message, Exception e) {
                System.err.println("[GroupChat] Realtime auth failed for " + channelName + ": " + message);
            }

            @Override
            public void onSubscriptionSucceeded(String channelName) {
                System.out.println("[GroupChat] Realtime connected: " + channelName);
            }

            @Override
            public void onEvent(PusherEvent event) {
                handleIncomingMessage(event);
            }

            @Override
            public void onError(String message, Exception e) {
                System.err.println("[GroupChat] Realtime channel error: " + message);
            }
        }, "message.sent");
    }

    private void handleIncomingMessage(PusherEvent event) {
        try {
            JSONObject m = new JSONObject(event.getData());
            if (m.getInt("user_id") == SessionManager.userId) return; // already shown via our own optimistic reload
            if (m.getInt("conversation_id") != activeConversationId) return; // stale event from a channel we've since left
            if (m.getInt("id") > maxSeenMessageId) maxSeenMessageId = m.getInt("id"); // arrived while open - counts as seen

            Platform.runLater(() -> {
                if (messagesBox.getChildren().size() == 1 && messagesBox.getChildren().get(0) instanceof Label) {
                    messagesBox.getChildren().clear(); // was showing "No messages yet."
                }
                messagesBox.getChildren().add(bubble(m));
                scrollToBottom();
            });
        } catch (Exception e) {
            System.err.println("[GroupChat] Realtime event parse error: " + e.getMessage());
        }
    }

    public void setGroupContext(int groupId, String groupName) {
        this.groupId = groupId;
        titleLabel.setText(groupName + " — Chat");
        loadConversation(-1);
    }

    private void loadConversation(int conversationId) {
        new Thread(() -> {
            String path = "/api/groups/" + groupId + "/chat-messages"
                + (conversationId > 0 ? "?conversation_id=" + conversationId : "");
            String body = get(path);
            if (body == null) {
                // Genuinely offline (not just a server error): fall back to
                // whatever this device last synced, instead of a hard error.
                // mainConversationId is a plain instance field, though, and
                // every screen navigation constructs a brand new
                // GroupChatController - opening Group Chat for a group for
                // the first time in a session while ALREADY offline meant
                // this was still 0 (never resolved by a live call), so the
                // cache was unreachable even though it existed. Falling
                // back to what was persisted the last time this group's
                // main conversation WAS resolved fixes that cold-start case.
                int resolvedConversationId = conversationId > 0 ? conversationId : mainConversationId;
                if (resolvedConversationId == 0) {
                    resolvedConversationId = dbManager.getRememberedMainConversationId(groupId);
                    if (resolvedConversationId != 0) mainConversationId = resolvedConversationId;
                }
                final int fallbackConversationId = resolvedConversationId;
                if (!NetworkUtil.isNetworkAvailable() && fallbackConversationId != 0) {
                    Platform.runLater(() -> {
                        activeConversationId = fallbackConversationId;
                        isRestricted = fallbackConversationId != mainConversationId;
                        ensureReadTrackingFor(activeConversationId);
                        renderOfflineFallback(fallbackConversationId);
                        startAutoRefresh();
                    });
                } else {
                    Platform.runLater(() -> showStatus("Couldn't load chat — check your connection."));
                }
                return;
            }
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> {
                    render(json);
                    startAutoRefresh();
                });
            } catch (Exception e) {
                System.err.println("[GroupChat] Parse error: " + e.getMessage());
            }
        }).start();
    }

    // Shows locally-cached messages (including anything queued while
    // offline, marked pending) instead of erroring out - "the offline
    // members can only use the desktop version and access only the saved
    // information" per the assignment brief.
    private void renderOfflineFallback(int conversationId) {
        syncStatusLabel.setText("● OFFLINE");
        syncStatusLabel.setStyle("-fx-text-fill: #D9483D; -fx-font-size: 12; -fx-font-weight: bold;");
        subtitleLabel.setText("You're offline — showing saved messages. New messages will send once you're back online.");

        threadListBox.getChildren().clear();
        Button groupChatItem = threadItem("👥  Group Chat", "Everyone in the group", conversationId == mainConversationId, mainConversationId);
        groupChatItem.setOnAction(e -> loadConversation(-1));
        threadListBox.getChildren().add(groupChatItem);

        List<ChatMessageItem> cached = dbManager.getCachedMessages(conversationId);
        messagesBox.getChildren().clear();
        if (cached.isEmpty()) {
            showStatus("No saved messages for this thread yet.");
        } else {
            boolean bannerInserted = false;
            for (ChatMessageItem m : cached) {
                if (m.getMessageId() > maxSeenMessageId) maxSeenMessageId = m.getMessageId();
                // Receiver-only, WhatsApp-style: your own messages never
                // trigger the divider, since you already know you sent them.
                boolean isOwn = m.getUserId() == SessionManager.userId;
                if (!bannerInserted && !isOwn && unreadThresholdMessageId > 0 && m.getMessageId() > unreadThresholdMessageId) {
                    messagesBox.getChildren().add(buildUnreadBanner());
                    bannerInserted = true;
                }
                messagesBox.getChildren().add(bubble(m.getUserId(), m.getAuthorName(), m.getBody(),
                    m.getCreatedAt(), m.isPending()));
            }
            scrollToBottom();
        }

        excludeToggleButton.setVisible(false);
        excludeToggleButton.setManaged(false);
        excludePanel.setVisible(false);
        excludePanel.setManaged(false);
        messageField.setPromptText("Type a message… (will send once you're back online)");
    }

    private void startAutoRefresh() {
        if (autoRefreshTimeline == null) {
            autoRefreshTimeline = new Timeline(new KeyFrame(AUTO_REFRESH_INTERVAL, e -> pollForUpdates()));
            autoRefreshTimeline.setCycleCount(Animation.INDEFINITE);
        }
        autoRefreshTimeline.playFromStart();
    }

    private void stopAutoRefresh() {
        if (autoRefreshTimeline != null) {
            autoRefreshTimeline.stop();
        }
    }

    // Silent background refresh: re-fetches whichever conversation is
    // currently active and updates the thread list + messages only. Unlike
    // loadConversation()'s render(), this never touches the composer, so it
    // can't reset an open exclude picker or wipe unsent checkbox selections.
    //
    // Also doubles as the offline->online detector: "if the internet gets
    // back, the system syncs and gets the new messages sent while offline"
    // (assignment requirement #8). The sync flush runs on *every* online
    // tick, not just the first one after reconnecting - gating it behind an
    // "only flush once" flag would silently stop retrying a message still
    // sitting unsent in the queue if a push ever failed while the
    // subsequent GET still succeeded. Flushing unconditionally is a
    // harmless no-op once the queue is empty.
    private void pollForUpdates() {
        int currentConversationId = activeConversationId;
        boolean onlineNow = NetworkUtil.isNetworkAvailable();

        if (!onlineNow) {
            Platform.runLater(this::markOffline);
            return;
        }

        new Thread(() -> {
            syncService.synchronizeLocalChanges();

            String path = "/api/groups/" + groupId + "/chat-messages"
                + (currentConversationId != mainConversationId ? "?conversation_id=" + currentConversationId : "");
            String body = get(path);
            if (body == null) return;
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> renderQuiet(json));
            } catch (Exception e) {
                System.err.println("[GroupChat] Poll parse error: " + e.getMessage());
            }
        }).start();
    }

    private void markOffline() {
        syncStatusLabel.setText("● OFFLINE");
        syncStatusLabel.setStyle("-fx-text-fill: #D9483D; -fx-font-size: 12; -fx-font-weight: bold;");
    }

    private void markOnline() {
        syncStatusLabel.setText("● ONLINE");
        syncStatusLabel.setStyle("-fx-text-fill: #3F9C6B; -fx-font-size: 12; -fx-font-weight: bold;");
    }

    /** Only reacts when the active conversation actually changed (a thread
     *  switch, or the very first load) - a silent poll of the SAME
     *  conversation must not reset the frozen divider threshold or it would
     *  never get a chance to show anything. */
    private void ensureReadTrackingFor(int newActiveConversationId) {
        if (newActiveConversationId == lastActiveConversationIdForReadTracking) return;
        markCurrentConversationRead();
        lastActiveConversationIdForReadTracking = newActiveConversationId;
        unreadThresholdMessageId = dbManager.getLastReadItemId("Conversation", newActiveConversationId);
        maxSeenMessageId = 0;
    }

    private void markCurrentConversationRead() {
        if (lastActiveConversationIdForReadTracking > 0 && maxSeenMessageId > 0) {
            dbManager.markRead("Conversation", lastActiveConversationIdForReadTracking, maxSeenMessageId);
        }
    }

    private void renderQuiet(JSONObject json) {
        mainConversationId = json.getInt("main_conversation_id");
        dbManager.rememberMainConversationId(groupId, mainConversationId);
        activeConversationId = json.getInt("active_conversation_id");
        isRestricted = json.getBoolean("is_restricted");
        ensureReadTrackingFor(activeConversationId);

        renderThreadList(json.getJSONArray("restricted_threads"));
        renderMessages(json.getJSONArray("messages"));
        subscribeRealtime(activeConversationId);
    }

    private void render(JSONObject json) {
        mainConversationId = json.getInt("main_conversation_id");
        dbManager.rememberMainConversationId(groupId, mainConversationId);
        activeConversationId = json.getInt("active_conversation_id");
        isRestricted = json.getBoolean("is_restricted");
        ensureReadTrackingFor(activeConversationId);

        renderThreadList(json.getJSONArray("restricted_threads"));
        renderMessages(json.getJSONArray("messages"));
        renderComposer(json.getJSONArray("group_members"));
        subscribeRealtime(activeConversationId);

        subtitleLabel.setText(isRestricted
            ? "Only selected members can see this thread."
            : "Everyone in this group sees your messages, unless you choose to exclude someone below.");
    }

    private void renderThreadList(JSONArray restrictedThreads) {
        threadListBox.getChildren().clear();

        Button groupChatItem = threadItem("👥  Group Chat", "Everyone in the group", activeConversationId == mainConversationId, mainConversationId);
        groupChatItem.setOnAction(e -> loadConversation(-1));
        threadListBox.getChildren().add(groupChatItem);

        if (restrictedThreads.length() > 0) {
            Label heading = new Label("RESTRICTED THREADS");
            heading.getStyleClass().add("muted-text");
            heading.setStyle("-fx-font-size: 10.5; -fx-font-weight: bold;");
            VBox.setMargin(heading, new Insets(14, 0, 4, 10));
            threadListBox.getChildren().add(heading);

            for (int i = 0; i < restrictedThreads.length(); i++) {
                JSONObject thread = restrictedThreads.getJSONObject(i);
                int id = thread.getInt("id");
                JSONArray excludedNames = thread.getJSONArray("excluded_names");
                List<String> names = new ArrayList<>();
                for (int j = 0; j < excludedNames.length(); j++) names.add(excludedNames.getString(j));
                String subtitle = names.isEmpty() ? "Some members excluded" : "Excludes " + String.join(", ", names);

                Button item = threadItem("🚫  Restricted Thread", subtitle, activeConversationId == id, id);
                item.setOnAction(e -> loadConversation(id));
                threadListBox.getChildren().add(item);
            }
        }
    }

    private Button threadItem(String title, String subtitle, boolean active) {
        return threadItem(title, subtitle, active, 0);
    }

    /** conversationId > 0 adds a small unread-count badge next to the title
     *  (see DatabaseManager.countUnreadMessages()) - 0 for callers like the
     *  group-switcher list where the id represents a GroupID, not a
     *  conversation, and no badge applies. */
    private Button threadItem(String title, String subtitle, boolean active, int conversationId) {
        VBox content = new VBox(1);
        Label titleLbl = new Label(title);
        titleLbl.setStyle("-fx-font-weight: bold; -fx-font-size: 12.5; -fx-text-fill: " + (active ? "white" : "#33455A") + ";");
        // wrapText inside a Button's graphic (inside a lazily-populated VBox
        // inside a ScrollPane) makes JavaFX compute a wild preferred height
        // for the Label - neither prefWidth nor a maxHeight clamp on the
        // ancestors fixes it. These subtitles are always short, so just
        // don't wrap: clip with an ellipsis instead, which never triggers
        // the buggy code path.
        Label subLbl = new Label(subtitle);
        subLbl.setMaxWidth(190);
        subLbl.setTextOverrun(javafx.scene.control.OverrunStyle.ELLIPSIS);
        subLbl.setStyle("-fx-font-size: 10.5; -fx-text-fill: " + (active ? "#D6E8F0" : "#6B8094") + ";");

        HBox titleRow = new HBox(6, titleLbl);
        titleRow.setAlignment(Pos.CENTER_LEFT);
        if (conversationId > 0 && !active) {
            // Not shown on the currently-open thread - you're already
            // looking at it, so nothing there is "unread" from this view.
            int unread = dbManager.countUnreadMessages(conversationId);
            if (unread > 0) {
                Label dot = new Label(unread > 99 ? "99+" : String.valueOf(unread));
                dot.setStyle("-fx-background-color: #D9483D; -fx-text-fill: white; -fx-font-size: 10; -fx-font-weight: bold; " +
                    "-fx-padding: 1 7; -fx-background-radius: 999;");
                titleRow.getChildren().add(dot);
            }
        }
        content.getChildren().addAll(titleRow, subLbl);

        Button btn = new Button();
        btn.setGraphic(content);
        btn.setMaxWidth(Double.MAX_VALUE);
        btn.setAlignment(Pos.CENTER_LEFT);
        btn.setStyle("-fx-background-color: " + (active ? "#26658C" : "transparent") + "; "
            + "-fx-background-radius: 8; -fx-padding: 8 10;");
        VBox.setMargin(btn, new Insets(2, 8, 2, 8));
        return btn;
    }

    private void renderMessages(JSONArray messages) {
        markOnline();

        messagesBox.getChildren().clear();
        if (messages.isEmpty()) {
            showStatus("No messages yet. Start the conversation.");
        } else {
            boolean bannerInserted = false;
            for (int i = 0; i < messages.length(); i++) {
                JSONObject m = messages.getJSONObject(i);
                int id = m.getInt("id");
                if (id > maxSeenMessageId) maxSeenMessageId = id;
                // Receiver-only, WhatsApp-style: never for the viewer's own messages.
                boolean isOwn = m.getInt("user_id") == SessionManager.userId;
                if (!bannerInserted && !isOwn && unreadThresholdMessageId > 0 && id > unreadThresholdMessageId) {
                    messagesBox.getChildren().add(buildUnreadBanner());
                    bannerInserted = true;
                }
                messagesBox.getChildren().add(bubble(m));
            }
            scrollToBottom();
        }

        // Keep the local cache warm so an offline fallback later has
        // something real to show instead of nothing.
        List<ChatMessageItem> toCache = new ArrayList<>();
        for (int i = 0; i < messages.length(); i++) {
            JSONObject m = messages.getJSONObject(i);
            toCache.add(new ChatMessageItem(m.getInt("id"), activeConversationId, m.getInt("user_id"),
                m.getString("author_name"), m.getString("body"), m.optString("created_at", ""), false));
        }
        new Thread(() -> dbManager.cacheMessages(activeConversationId, toCache)).start();
    }

    private void renderComposer(JSONArray groupMembers) {
        // Only the main group thread can spawn new restricted threads —
        // matches the web (a reply inside a restricted thread reuses its
        // exclusion set automatically and can't exclude further).
        excludeToggleButton.setVisible(!isRestricted);
        excludeToggleButton.setManaged(!isRestricted);
        excludePanel.setVisible(false);
        excludePanel.setManaged(false);

        excludeCheckboxesBox.getChildren().clear();
        excludeCheckboxes.clear();

        for (int i = 0; i < groupMembers.length(); i++) {
            JSONObject member = groupMembers.getJSONObject(i);
            CheckBox cb = new CheckBox(member.getString("name"));
            cb.setUserData(member.getInt("id"));
            cb.setStyle("-fx-font-size: 12.5;");
            excludeCheckboxes.add(cb);
            excludeCheckboxesBox.getChildren().add(cb);
        }

        messageField.setPromptText(isRestricted ? "Reply in this restricted thread…" : "Type a message…");
    }

    @FXML
    protected void onToggleExclude() {
        boolean nowVisible = !excludePanel.isVisible();
        excludePanel.setVisible(nowVisible);
        excludePanel.setManaged(nowVisible);
    }

    /** Same WhatsApp-green "── NEW MESSAGES ──" divider as TopicController's,
     *  shown once above the first RECEIVED message (never the viewer's own)
     *  newer than the frozen read marker. */
    private HBox buildUnreadBanner() {
        javafx.scene.layout.Region leftLine = new javafx.scene.layout.Region();
        leftLine.setStyle("-fx-background-color: #3F9C6B; -fx-pref-height: 1;");
        HBox.setHgrow(leftLine, javafx.scene.layout.Priority.ALWAYS);
        javafx.scene.layout.Region rightLine = new javafx.scene.layout.Region();
        rightLine.setStyle("-fx-background-color: #3F9C6B; -fx-pref-height: 1;");
        HBox.setHgrow(rightLine, javafx.scene.layout.Priority.ALWAYS);


        Label label = new Label("NEW MESSAGES");
        label.setStyle("-fx-text-fill: #3F9C6B; -fx-font-size: 10.5; -fx-font-weight: bold; -fx-padding: 0 10;");

        HBox banner = new HBox(0, leftLine, label, rightLine);
        banner.setAlignment(Pos.CENTER);
        banner.setStyle("-fx-padding: 10 4;");
        return banner;
    }

    private VBox bubble(JSONObject m) {
        return bubble(m.getInt("user_id"), m.getString("author_name"), m.getString("body"),
            m.optString("created_at", ""), false);
    }

    private VBox bubble(int userId, String authorName, String body, String createdAt, boolean pending) {
        boolean isOwn = userId == SessionManager.userId;

        VBox bubble = new VBox(3);
        bubble.setMaxWidth(420);
        bubble.setAlignment(Pos.TOP_LEFT);
        bubble.setStyle("-fx-background-color: " + (isOwn ? "#26658C" : "white") + "; " +
            "-fx-background-radius: 10; -fx-padding: 10 14; " +
            "-fx-effect: dropshadow(gaussian, #e3e6ee, 4, 0, 0, 1);" +
            (pending ? " -fx-border-style: segments(4,3); -fx-border-color: #ffcc00; -fx-border-radius: 10;" : ""));

        Label author = new Label(authorName + "  ·  " + (pending ? "Pending sync…" : createdAt));
        author.setStyle("-fx-font-size: 10.5; -fx-font-weight: bold; -fx-text-fill: "
            + (isOwn ? "#cfe0ea" : "#6B8094") + ";");

        Label bodyLabel = new Label(body);
        bodyLabel.setWrapText(true);
        bodyLabel.setStyle("-fx-font-size: 13; -fx-text-fill: " + (isOwn ? "white" : "#33455A") + ";");

        bubble.getChildren().addAll(author, bodyLabel);

        VBox wrapper = new VBox(bubble);
        wrapper.setAlignment(isOwn ? Pos.CENTER_RIGHT : Pos.CENTER_LEFT);
        return wrapper;
    }

    private void showStatus(String text) {
        messagesBox.getChildren().clear();
        Label l = new Label(text);
        l.setStyle("-fx-text-fill: #6B8094; -fx-font-size: 13;");
        messagesBox.getChildren().add(l);
    }

    private void scrollToBottom() {
        Platform.runLater(() -> scrollPane.setVvalue(1.0));
    }

    @FXML
    protected void onSend() {
        String text = messageField.getText().trim();
        if (text.isEmpty()) return;

        List<Integer> excludeIds = new ArrayList<>();
        if (!isRestricted) {
            for (CheckBox cb : excludeCheckboxes) {
                if (cb.isSelected()) excludeIds.add((Integer) cb.getUserData());
            }
        }

        messageField.clear();
        messageField.setDisable(true);

        new Thread(() -> {
            if (!NetworkUtil.isNetworkAvailable()) {
                queueOfflineSend(text, excludeIds);
                return;
            }

            Integer resultConversationId = post(text, excludeIds);
            Platform.runLater(() -> {
                messageField.setDisable(false);
                messageField.requestFocus();
                if (resultConversationId != null) {
                    // If this message spawned/landed in a different
                    // conversation (a new or reused restricted thread),
                    // switch straight to it so the sender sees where their
                    // message actually went.
                    loadConversation(resultConversationId == mainConversationId ? -1 : resultConversationId);
                } else if (!NetworkUtil.isNetworkAvailable()) {
                    // Connectivity dropped between the check above and the
                    // request actually landing - still don't lose the draft.
                    queueOfflineSend(text, excludeIds);
                } else {
                    System.err.println("[GroupChat] Failed to send message");
                }
            });
        }).start();
    }

    // Composed while offline: cached locally (marked pending) and logged to
    // the SyncQueue instead of being lost, then shown immediately so the
    // sender can see their message wasn't dropped.
    private void queueOfflineSend(String text, List<Integer> excludeIds) {
        Integer payloadConversationId = isRestricted ? activeConversationId : null;
        String excludeJson = new JSONArray(excludeIds).toString();
        String authorName = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;

        dbManager.queuePendingMessage(groupId, activeConversationId, SessionManager.userId, authorName,
            text, payloadConversationId, excludeJson);

        Platform.runLater(() -> {
            messageField.setDisable(false);
            messageField.requestFocus();
            markOffline();
            if (messagesBox.getChildren().size() == 1 && messagesBox.getChildren().get(0) instanceof Label) {
                messagesBox.getChildren().clear();
            }
            messagesBox.getChildren().add(bubble(SessionManager.userId, authorName, text, "", true));
            scrollToBottom();
        });
    }

    private String get(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            if (conn.getResponseCode() != 200) return null;
            return readBody(conn);
        } catch (Exception e) {
            System.err.println("[GroupChat] GET error: " + e.getMessage());
            return null;
        }
    }

    private Integer post(String messageBody, List<Integer> excludeIds) {
        try {
            URL url = URI.create(BASE_URL + "/api/groups/" + groupId + "/chat-messages").toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setDoOutput(true);

            JSONObject payload = new JSONObject();
            payload.put("body", messageBody);
            if (isRestricted) {
                payload.put("conversation_id", activeConversationId);
            } else if (!excludeIds.isEmpty()) {
                payload.put("exclude", new JSONArray(excludeIds));
            }
            try (OutputStream os = conn.getOutputStream()) {
                os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
            }

            int code = conn.getResponseCode();
            if (code != 200 && code != 201) return null;
            JSONObject response = new JSONObject(readBody(conn));
            return response.getInt("conversation_id");
        } catch (Exception e) {
            System.err.println("[GroupChat] POST error: " + e.getMessage());
            return null;
        }
    }

    private String readBody(HttpURLConnection conn) throws Exception {
        BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
        StringBuilder response = new StringBuilder();
        String line;
        while ((line = in.readLine()) != null) response.append(line);
        in.close();
        return response.toString();
    }

}
