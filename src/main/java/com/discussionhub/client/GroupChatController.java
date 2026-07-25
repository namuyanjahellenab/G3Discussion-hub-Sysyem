package com.discussionhub.client;

import com.discussionhub.client.database.ChatMessageItem;
import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import com.discussionhub.client.utils.TextUtil;
import com.pusher.client.Pusher;
import com.pusher.client.PusherOptions;
import com.pusher.client.channel.PrivateChannelEventListener;
import com.pusher.client.channel.PusherEvent;
import com.pusher.client.util.HttpChannelAuthorizer;
import com.discussionhub.client.utils.WindowUtil;
import com.discussionhub.client.utils.AppConfig;
import javafx.animation.Animation;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.CheckBox;
import javafx.scene.control.Label;
import javafx.scene.control.ContextMenu;
import javafx.scene.control.MenuItem;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.TextField;
import javafx.scene.control.TextInputDialog;
import javafx.scene.control.Tooltip;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.stage.FileChooser;
import javafx.stage.Stage;
import javafx.util.Duration;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.File;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.net.URLConnection;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.Iterator;
import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;

// The desktop equivalent of the web's Group Chat (student.messages / GroupChatController):
// main group-wide thread + any number of restricted threads (a thread that
// excludes specific group members). Mirrors the web's inline "Exclude
// members" composer panel rather than a modal dialog.
public class GroupChatController {

    private static final String BASE_URL = AppConfig.BASE_URL;

    // Must match REVERB_APP_KEY / REVERB_HOST / REVERB_PORT in the server's
    // .env - this is the same Reverb server the web client's
    // resources/js/echo.js connects to, just over the raw Pusher-protocol
    // client instead of Laravel Echo. NOTE: Reverb is not yet deployed as
    // its own Railway service, so these still resolve to a local dev
    // server via AppConfig - realtime push will silently fail to connect
    // until that's stood up; the 10s poll fallback covers the gap.
    private static final String REVERB_APP_KEY = AppConfig.REVERB_APP_KEY;
    private static final String REVERB_HOST = AppConfig.REVERB_HOST;
    private static final int REVERB_WS_PORT = AppConfig.REVERB_WS_PORT;

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
    @FXML private Label attachedFileLabel;
    @FXML private Button removeAttachmentButton;
    @FXML private Label replyContextLabel;
    @FXML private Button cancelReplyButton;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private int groupId;
    private int mainConversationId;
    private int activeConversationId;
    private boolean isRestricted;
    // Set by post()/postMultipart() when the server actively rejects a send
    // (e.g. blacklisted) so onSend() can show the real reason instead of a
    // silent failure - null means either success or a plain connection error.
    private String lastSendError;
    private final List<CheckBox> excludeCheckboxes = new ArrayList<>();
    private File selectedAttachment;
    // WhatsApp-style tagging: which message (if any) is being replied to -
    // rides along as parent_message_id on the next send, then clears itself,
    // same lifecycle as selectedAttachment above.
    private Integer replyToId;

    // Same whitelist as TopicController's reply composer and
    // AttachmentUploader/GroupChatApiController's server-side validation.
    private static final String[] ATTACHMENT_EXTENSIONS = {
        "*.pdf", "*.doc", "*.docx", "*.ppt", "*.pptx", "*.png", "*.jpg", "*.jpeg", "*.zip"
    };
    private static final long MAX_ATTACHMENT_BYTES = 20L * 1024 * 1024;

    // Local "unread" tracking (see DatabaseManager's ReadState table),
    // mirroring TopicController's - unreadThresholdMessageId is frozen the
    // moment a conversation becomes active (not updated live), so the
    // NEW MESSAGES divider doesn't jump around while it's open; switching
    // to a different thread (or leaving the screen) marks the previous one
    // read using maxSeenMessageId, so it's gone next time it's opened.
    private int lastActiveConversationIdForReadTracking = 0;
    private int unreadThresholdMessageId = 0;
    private int maxSeenMessageId = 0;
    private boolean liveUnreadBannerInserted = false;

    // Diffs the message list against what's already rendered instead of
    // clearing and rebuilding messagesBox on every 10s poll cycle (see
    // renderMessages()) - mirrors TopicController's renderedReplyRows, which
    // was the fix for this exact "screen blinks every 10 seconds" symptom
    // there.
    private final Map<Integer, Region> renderedMessageRows = new LinkedHashMap<>();

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

    private static final String DASHBOARD_ENDPOINT = "/api/dashboard";

    private void loadMyGroups() {
        new Thread(() -> {
            String body = get(DASHBOARD_ENDPOINT);
            if (body != null) {
                dbManager.cacheApiResponse(DASHBOARD_ENDPOINT, body);
            } else {
                // Offline (or the request just failed): fall back to the
                // last-known joined-groups list instead of leaving the
                // switcher empty - matches the generic cache-then-fallback
                // pattern ForumController uses for the same reason.
                body = dbManager.getCachedApiResponse(DASHBOARD_ENDPOINT);
            }
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
            boolean active = id == groupId;
            int unreadCount = group.optInt("unread_count", 0);
            Button item = threadItemWithUnreadCount("📚  " + name, "Tap to switch groups", active, unreadCount);
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
            .setUseTLS(AppConfig.REVERB_USE_TLS)
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
            int id = m.getInt("id");
            if (m.getInt("user_id") == SessionManager.userId) return; // already shown via our own optimistic reload
            if (m.getInt("conversation_id") != activeConversationId) return; // stale event from a channel we've since left
            if (id > maxSeenMessageId) maxSeenMessageId = id; // arrived while open - counts as seen
            if (renderedMessageRows.containsKey(id)) return; // already shown (e.g. beat the next poll here)

            Platform.runLater(() -> {
                if (messagesBox.getChildren().size() == 1 && messagesBox.getChildren().get(0) instanceof Label) {
                    messagesBox.getChildren().clear(); // was showing "No messages yet."
                }
                Region row = bubble(m);
                messagesBox.getChildren().add(row);
                // Registered here too (not just in renderMessages()'s poll
                // diff) - otherwise the next 10s poll would see this id as
                // "never rendered" and add a second, duplicate bubble for it.
                renderedMessageRows.put(id, row);
                scrollToBottom();
            });
        } catch (Exception e) {
            System.err.println("[GroupChat] Realtime event parse error: " + e.getMessage());
        }
    }

    public void setGroupContext(int groupId, String groupName) {
        this.groupId = groupId;
        // mainConversationId belongs to whichever group was loaded last, not
        // this one - without resetting it here, switching groups while
        // offline kept falling back to the PREVIOUS group's remembered
        // conversation (since it's non-zero, the "not yet resolved" check in
        // loadConversation() never re-looks-up the new group's own id), so
        // every group's offline switch showed the same stale messages.
        this.mainConversationId = 0;
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

        // No "Group Chat / Everyone in the group" item here (unlike the
        // online thread list) - offline only ever has this one conversation
        // to show, so a button that just reloads the same thread you're
        // already looking at is dead weight, not a real switch target.
        threadListBox.getChildren().clear();

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

        // Same cadence as the message poll above - keeps other groups'
        // unread badges in the switcher current too, not just this group's
        // own threads, so a message landing in a group you're not currently
        // viewing shows up within the same ~10s window.
        loadMyGroups();
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
        // Forget what's tracked as "already rendered" - this is a genuinely
        // different conversation, so renderMessages() must treat every
        // incoming message as new instead of diffing against the previous
        // thread's rows. The actual bubbles must be wiped here too, not just
        // the tracking map: renderMessages()'s diff can only remove rows it
        // still has a record of, and that record was just cleared above, so
        // without this an empty (or different) conversation kept showing
        // whatever the previously-viewed one had on screen.
        renderedMessageRows.clear();
        messagesBox.getChildren().clear();
        liveUnreadBannerInserted = false;
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

    /** conversationId > 0 adds a small unread-count badge next to the title,
     *  looked up from the local cache (see DatabaseManager.
     *  countUnreadMessages()) - 0 for callers like the group-switcher list
     *  where the id represents a GroupID, not a conversation. */
    private Button threadItem(String title, String subtitle, boolean active, int conversationId) {
        int unread = (conversationId > 0 && !active) ? dbManager.countUnreadMessages(conversationId) : 0;
        return threadItemWithUnreadCount(title, subtitle, active, unread);
    }

    /** Group-switcher variant: the unread count comes straight from the
     *  server (StudentDataApiController::dashboard()'s per-group
     *  unread_count), since the local cache has no data for a group the
     *  desktop hasn't opened yet - mirrors student.messages.blade.php's
     *  $groupUnreadCounts. */
    private Button threadItemWithUnreadCount(String title, String subtitle, boolean active, int unreadCount) {
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
        if (!active && unreadCount > 0) {
            // Not shown on the currently-open thread - you're already
            // looking at it, so nothing there is "unread" from this view.
            Label dot = new Label(unreadCount > 99 ? "99+" : String.valueOf(unreadCount));
            dot.setStyle("-fx-background-color: #D9483D; -fx-text-fill: white; -fx-font-size: 10; -fx-font-weight: bold; " +
                "-fx-padding: 1 7; -fx-background-radius: 999;");
            titleRow.getChildren().add(dot);
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

    /**
     * Diffs the incoming message list against what's already rendered
     * instead of clearing and rebuilding messagesBox every 10s poll cycle -
     * a message that's already shown is left completely alone (no remove,
     * no re-add), so there's nothing to blink while reading or typing.
     * Messages only ever append (MessageID is auto-increment, no
     * re-ordering in the API response), so a never-seen id can safely be
     * added at the end without touching anyone else's position.
     */
    private void renderMessages(JSONArray messages) {
        markOnline();

        Set<Integer> incomingIds = new LinkedHashSet<>();
        for (int i = 0; i < messages.length(); i++) {
            incomingIds.add(messages.getJSONObject(i).getInt("id"));
        }

        Iterator<Map.Entry<Integer, Region>> it = renderedMessageRows.entrySet().iterator();
        while (it.hasNext()) {
            Map.Entry<Integer, Region> entry = it.next();
            if (!incomingIds.contains(entry.getKey())) {
                messagesBox.getChildren().remove(entry.getValue());
                it.remove();
            }
        }

        // First render for this conversation (or coming from an empty/
        // placeholder state) - clear whatever's there (e.g. "No messages
        // yet") before appending the real rows below.
        if (!messages.isEmpty() && renderedMessageRows.isEmpty() && !messagesBox.getChildren().isEmpty()) {
            messagesBox.getChildren().clear();
        }

        for (int i = 0; i < messages.length(); i++) {
            JSONObject m = messages.getJSONObject(i);
            int id = m.getInt("id");
            if (id > maxSeenMessageId) maxSeenMessageId = id;

            if (renderedMessageRows.containsKey(id)) continue; // untouched - already shown

            // Receiver-only, WhatsApp-style: never for the viewer's own messages.
            boolean isOwn = m.getInt("user_id") == SessionManager.userId;
            if (!liveUnreadBannerInserted && !isOwn && unreadThresholdMessageId > 0 && id > unreadThresholdMessageId) {
                messagesBox.getChildren().add(buildUnreadBanner());
                liveUnreadBannerInserted = true;
            }

            Region row = bubble(m);
            messagesBox.getChildren().add(row);
            renderedMessageRows.put(id, row);
        }

        if (messages.isEmpty() && messagesBox.getChildren().isEmpty()) {
            showStatus("No messages yet. Start the conversation.");
        }

        // Keep the local cache warm so an offline fallback later has
        // something real to show instead of nothing.
        List<ChatMessageItem> toCache = new ArrayList<>();
        for (int i = 0; i < messages.length(); i++) {
            JSONObject m = messages.getJSONObject(i);
            toCache.add(new ChatMessageItem(m.getInt("id"), activeConversationId, m.getInt("user_id"),
                m.getString("author_name"), m.getString("body"),
                m.optString("created_at_iso", m.optString("created_at", "")), false));
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
        Region leftLine = new Region();
        leftLine.setStyle("-fx-background-color: #3F9C6B; -fx-pref-height: 1;");
        HBox.setHgrow(leftLine, javafx.scene.layout.Priority.ALWAYS);
        Region rightLine = new Region();
        rightLine.setStyle("-fx-background-color: #3F9C6B; -fx-pref-height: 1;");
        HBox.setHgrow(rightLine, javafx.scene.layout.Priority.ALWAYS);


        Label label = new Label("NEW MESSAGES");
        label.setStyle("-fx-text-fill: #3F9C6B; -fx-font-size: 10.5; -fx-font-weight: bold; -fx-padding: 0 10;");

        HBox banner = new HBox(0, leftLine, label, rightLine);
        banner.setAlignment(Pos.CENTER);
        banner.setStyle("-fx-padding: 10 4;");
        return banner;
    }

    private Region bubble(JSONObject m) {
        return bubble(m.getInt("id"), m.getInt("user_id"), m.getString("author_name"), m.getString("body"),
            m.optString("created_at_iso", m.optString("created_at", "")), false,
            m.isNull("attachment_url") ? null : m.optString("attachment_url", null),
            m.optString("attachment_type", null),
            m.optString("attachment_name", null),
            true,
            m.isNull("parent_message_author") ? null : m.optString("parent_message_author", null),
            m.optString("parent_message_snippet", null));
    }

    private Region bubble(int userId, String authorName, String body, String createdAt, boolean pending) {
        return bubble(-1, userId, authorName, body, createdAt, pending, null, null, null, false, null, null);
    }

    /**
     * attachmentUrl null means "no attachment" - was previously the only
     * overload, which is why a message sent with a file but no text (see
     * GroupChatApiController::index()) rendered as an empty bubble: there
     * was nothing here that could show one at all. canReply is false for
     * cached/offline/pending bubbles (messageId is -1 or not yet real for
     * those), same as TopicController disabling all moderation actions on
     * cached replies.
     */
    private Region bubble(int messageId, int userId, String authorName, String body, String createdAt, boolean pending,
                         String attachmentUrl, String attachmentType, String attachmentName,
                         boolean canReply, String parentAuthor, String parentSnippet) {
        boolean isOwn = userId == SessionManager.userId;

        VBox bubble = new VBox(3);
        bubble.setMaxWidth(420);
        bubble.setAlignment(Pos.TOP_LEFT);
        bubble.setStyle("-fx-background-color: " + (isOwn ? "#26658C" : "white") + "; " +
            "-fx-background-radius: 10; -fx-padding: 10 14; " +
            "-fx-effect: dropshadow(gaussian, #e3e6ee, 4, 0, 0, 1);" +
            (pending ? " -fx-border-style: segments(4,3); -fx-border-color: #ffcc00; -fx-border-radius: 10;" : ""));

        Label author = new Label(authorName + "  ·  " + (pending ? "Pending sync…" : TextUtil.timeAgo(createdAt)));
        author.setStyle("-fx-font-size: 10.5; -fx-font-weight: bold; -fx-text-fill: "
            + (isOwn ? "#cfe0ea" : "#6B8094") + ";");
        bubble.getChildren().add(author);

        if (parentAuthor != null) {
            Label quote = new Label("↩ Replying to " + parentAuthor + ": " + (parentSnippet == null ? "" : parentSnippet));
            quote.setWrapText(true);
            quote.setStyle("-fx-background-color: " + (isOwn ? "rgba(255,255,255,0.2)" : "-luna-lightest")
                + "; -fx-text-fill: " + (isOwn ? "white" : "-luna-dark")
                + "; -fx-padding: 3 8; -fx-background-radius: 6; -fx-font-size: 11;");
            bubble.getChildren().add(quote);
        }

        if (body != null && !body.isBlank()) {
            Label bodyLabel = new Label(body);
            bodyLabel.setWrapText(true);
            bodyLabel.setStyle("-fx-font-size: 13; -fx-text-fill: " + (isOwn ? "white" : "#33455A") + ";");
            bubble.getChildren().add(bodyLabel);
        }

        if (attachmentUrl != null) {
            bubble.getChildren().add(buildAttachmentNode(attachmentUrl,
                attachmentType != null ? attachmentType : "file",
                attachmentName != null ? attachmentName : "attachment", isOwn));
        }

        // The "⋮" menu sits outside the colored bubble entirely, as its own
        // element beside it - matches chat-bubble.blade.php's actual markup,
        // where .chat-bubble__actions is a sibling of .chat-bubble__content,
        // not something nested inside the message card itself.
        HBox row = new HBox(4);
        row.setAlignment(isOwn ? Pos.CENTER_RIGHT : Pos.CENTER_LEFT);
        if (canReply) {
            Button menu = buildActionsMenu(messageId, userId, authorName, body, isOwn);
            if (isOwn) {
                row.getChildren().addAll(menu, bubble);
            } else {
                row.getChildren().addAll(bubble, menu);
            }
        } else {
            row.getChildren().add(bubble);
        }
        return row;
    }

    /**
     * Deliberately simpler than TopicController's buildAttachmentNode (no
     * image preview, no offline caching yet) - a clickable name plus a
     * download button is what was actually missing (the bug: an attachment
     * with no visible name at all). url is a plain public storage path from
     * the server (see GroupChatApiController), so it opens directly.
     */
    private javafx.scene.Node buildAttachmentNode(String url, String type, String name, boolean isOwn) {
        String fullUrl = url.startsWith("http") ? url : BASE_URL + url;
        String linkColor = isOwn ? "#cfe0ea" : "#26658C";

        Label fileLink = new Label("📎 " + name);
        fileLink.setWrapText(true);
        fileLink.setStyle("-fx-text-fill: " + linkColor + "; -fx-font-weight: bold; -fx-font-size: 12; "
            + "-fx-cursor: hand; -fx-underline: true;");
        fileLink.setOnMouseClicked(e -> {
            try {
                java.awt.Desktop.getDesktop().browse(com.discussionhub.client.utils.AttachmentCache.toSafeUri(fullUrl));
            } catch (Exception ex) {
                System.err.println("[GroupChat] Couldn't open attachment: " + ex.getMessage());
            }
        });

        return fileLink;
    }

    /** Mirrors chat-bubble.blade.php's "⋮" menu: one small toggle button
     *  that opens a dropdown of Reply/Exclude sender/Edit/Delete. Built from
     *  a plain Button + ContextMenu rather than JavaFX's MenuButton control -
     *  MenuButton's default skin renders a label *and* its own built-in
     *  dropdown arrow side by side, and squeezed into a small fixed-size
     *  button the arrow was crowding out the "⋮" text entirely, leaving
     *  only a faint caret visible instead. A plain Button showing the
     *  ContextMenu itself has no such built-in arrow to fight. Exclude only
     *  shows for someone else's message in the (excludable) main thread;
     *  Edit/Delete only for your own. */
    private Button buildActionsMenu(int messageId, int userId, String authorName, String body, boolean isOwn) {
        ContextMenu menu = new ContextMenu();

        String itemStyle = "-fx-font-size: 11;";

        MenuItem reply = new MenuItem("↩ Reply");
        reply.setStyle(itemStyle);
        reply.setOnAction(e -> startReplyTo(messageId, authorName, body == null ? "" : body));
        menu.getItems().add(reply);

        if (!isOwn && !isRestricted) {
            MenuItem exclude = new MenuItem("🚫 Exclude sender");
            exclude.setStyle(itemStyle);
            exclude.setOnAction(e -> excludeMember(userId));
            menu.getItems().add(exclude);
        }
        if (isOwn) {
            // Always available on your own messages, even an attachment-only
            // one with no text yet - editing it there just adds a caption.
            MenuItem edit = new MenuItem("✎ Edit");
            edit.setStyle(itemStyle);
            edit.setOnAction(e -> onEditMessage(messageId, body == null ? "" : body));
            menu.getItems().add(edit);

            MenuItem delete = new MenuItem("🗑 Delete");
            delete.setStyle(itemStyle);
            delete.setOnAction(e -> onDeleteMessage(messageId));
            menu.getItems().add(delete);
        }

        Button toggle = new Button("⋮");
        toggle.setStyle("-fx-background-color: transparent; -fx-text-fill: #33455A; -fx-font-size: 22; "
            + "-fx-font-weight: bold; -fx-min-width: 40; -fx-min-height: 40; -fx-max-width: 40; -fx-max-height: 40; "
            + "-fx-padding: 0; -fx-cursor: hand;");
        toggle.setOnAction(e -> menu.show(toggle, javafx.geometry.Side.BOTTOM, 0, 0));
        return toggle;
    }

    private Button actionButton(String icon, Runnable action) {
        Button b = new Button(icon);
        b.setStyle("-fx-background-color: transparent; -fx-padding: 0 0 0 2; -fx-font-size: 11;");
        b.setOnAction(e -> action.run());
        return b;
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

    /** Mirrors TopicController's startReplyTo() - shows a small "Replying
     *  to X: snippet" strip above the composer and remembers the target
     *  message id to send as parent_message_id on the next message. */
    private void startReplyTo(int messageId, String authorName, String body) {
        replyToId = messageId;
        String snippet = body.length() > 40 ? body.substring(0, 40) + "…" : body;
        replyContextLabel.setText("↩ Replying to " + authorName + ": " + snippet);
        replyContextLabel.setVisible(true);
        replyContextLabel.setManaged(true);
        cancelReplyButton.setVisible(true);
        cancelReplyButton.setManaged(true);
        messageField.requestFocus();
    }

    @FXML
    protected void onCancelReply() {
        replyToId = null;
        replyContextLabel.setVisible(false);
        replyContextLabel.setManaged(false);
        cancelReplyButton.setVisible(false);
        cancelReplyButton.setManaged(false);
    }

    /** Matches chat-bubble.blade.php's "Exclude sender" action - opens the
     *  panel and pre-checks that member, same as clicking their checkbox
     *  by hand. */
    private void excludeMember(int userId) {
        excludePanel.setVisible(true);
        excludePanel.setManaged(true);
        for (CheckBox cb : excludeCheckboxes) {
            if (cb.getUserData() instanceof Integer id && id == userId) {
                cb.setSelected(true);
                break;
            }
        }
    }

    /** Only the text can change here - an already-sent attachment can't be
     *  swapped out, same limitation the web's Edit action has. */
    private void onEditMessage(int messageId, String currentBody) {
        TextInputDialog dialog = new TextInputDialog(currentBody);
        dialog.setTitle("Edit Message");
        dialog.setHeaderText(null);
        dialog.setContentText("Message:");
        dialog.showAndWait().ifPresent(newBody -> {
            String trimmed = newBody.trim();
            if (trimmed.isEmpty() || trimmed.equals(currentBody)) return;

            new Thread(() -> {
                String error = patchMessage(messageId, trimmed);
                Platform.runLater(() -> {
                    if (error != null) {
                        Alert alert = new Alert(Alert.AlertType.WARNING, error);
                        alert.setHeaderText(null);
                        alert.showAndWait();
                    } else {
                        // The diff in renderMessages() skips any id it's
                        // already rendered, so an in-place body change would
                        // otherwise never show up on the next refresh -
                        // forgetting this row here forces it to be rebuilt
                        // with the freshly-edited text.
                        Region oldRow = renderedMessageRows.remove(messageId);
                        if (oldRow != null) messagesBox.getChildren().remove(oldRow);
                        loadConversation(activeConversationId == mainConversationId ? -1 : activeConversationId);
                    }
                });
            }).start();
        });
    }

    private void onDeleteMessage(int messageId) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION, "Delete this message?");
        confirm.showAndWait().ifPresent(btn -> {
            if (btn != javafx.scene.control.ButtonType.OK) return;
            new Thread(() -> {
                String error = deleteMessage(messageId);
                Platform.runLater(() -> {
                    if (error != null) {
                        Alert alert = new Alert(Alert.AlertType.WARNING, error);
                        alert.setHeaderText(null);
                        alert.showAndWait();
                    } else {
                        Region row = renderedMessageRows.remove(messageId);
                        if (row != null) messagesBox.getChildren().remove(row);
                    }
                });
            }).start();
        });
    }

    private String patchMessage(int messageId, String newBody) {
        try {
            // Java's HttpURLConnection has a hardcoded whitelist of methods
            // and rejects "PATCH" outright (ProtocolException: Invalid HTTP
            // method: PATCH) - this was the actual cause of Edit always
            // failing. Sending it as POST with a "_method" override field
            // instead is Laravel's own built-in spoofing mechanism (the same
            // thing @method('PATCH') does for an HTML form), enabled by
            // default via Request::enableHttpMethodParameterOverride().
            HttpURLConnection conn = (HttpURLConnection) URI.create(BASE_URL + "/api/group-messages/" + messageId).toURL().openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setDoOutput(true);
            try (OutputStream os = conn.getOutputStream()) {
                JSONObject payload = new JSONObject().put("body", newBody).put("_method", "PATCH");
                os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
            }
            int code = conn.getResponseCode();
            if (code == 200) return null;
            return errorMessageFrom(conn);
        } catch (Exception e) {
            return "Couldn't reach the server — check your connection.";
        }
    }

    private String deleteMessage(int messageId) {
        try {
            HttpURLConnection conn = (HttpURLConnection) URI.create(BASE_URL + "/api/group-messages/" + messageId).toURL().openConnection();
            conn.setRequestMethod("DELETE");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            int code = conn.getResponseCode();
            if (code == 200) return null;
            return errorMessageFrom(conn);
        } catch (Exception e) {
            return "Couldn't reach the server — check your connection.";
        }
    }

    private String errorMessageFrom(HttpURLConnection conn) {
        try (BufferedReader in = new BufferedReader(new InputStreamReader(conn.getErrorStream(), StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = in.readLine()) != null) sb.append(line);
            return new JSONObject(sb.toString()).optString("message", "The server rejected that request.");
        } catch (Exception e) {
            return "The server rejected that request.";
        }
    }

    @FXML
    protected void onAttachFile() {
        FileChooser chooser = new FileChooser();
        chooser.setTitle("Attach a file");
        chooser.getExtensionFilters().add(new FileChooser.ExtensionFilter(
            "Supported files", ATTACHMENT_EXTENSIONS));
        File file = chooser.showOpenDialog(messageField.getScene().getWindow());
        if (file == null) return;

        if (file.length() > MAX_ATTACHMENT_BYTES) {
            Alert alert = new Alert(Alert.AlertType.WARNING, "That file is larger than 20 MB — pick a smaller one.");
            alert.setHeaderText(null);
            alert.showAndWait();
            return;
        }

        selectedAttachment = file;
        attachedFileLabel.setText("📎 " + file.getName());
        attachedFileLabel.setVisible(true);
        attachedFileLabel.setManaged(true);
        removeAttachmentButton.setVisible(true);
        removeAttachmentButton.setManaged(true);
    }

    @FXML
    protected void onRemoveAttachment() {
        selectedAttachment = null;
        attachedFileLabel.setVisible(false);
        attachedFileLabel.setManaged(false);
        removeAttachmentButton.setVisible(false);
        removeAttachmentButton.setManaged(false);
    }

    @FXML
    protected void onSend() {
        String text = messageField.getText().trim();
        File attachment = selectedAttachment;
        if (text.isEmpty() && attachment == null) return;

        List<Integer> excludeIds = new ArrayList<>();
        if (!isRestricted) {
            for (CheckBox cb : excludeCheckboxes) {
                if (cb.isSelected()) excludeIds.add((Integer) cb.getUserData());
            }
        }

        // Captured and cleared immediately, same as messageField.clear()
        // below - the offline queue has no column for it, so a reply typed
        // while offline just sends as a plain message rather than blocking
        // the whole send the way a missing attachment does.
        Integer parentId = replyToId;
        onCancelReply();

        messageField.clear();
        messageField.setDisable(true);

        new Thread(() -> {
            if (!NetworkUtil.isNetworkAvailable()) {
                if (attachment != null) {
                    // Unlike a plain text message, an attachment can't be
                    // queued for later - there's no offline upload path, so
                    // failing loudly here beats silently dropping the file
                    // the user just picked.
                    Platform.runLater(() -> {
                        messageField.setDisable(false);
                        messageField.setText(text);
                        Alert alert = new Alert(Alert.AlertType.WARNING,
                            "Attachments can't be sent while offline — connect and try again.");
                        alert.setHeaderText(null);
                        alert.showAndWait();
                    });
                    return;
                }
                queueOfflineSend(text, excludeIds);
                return;
            }

            Integer resultConversationId = attachment != null
                ? postMultipart(text, excludeIds, attachment, parentId)
                : post(text, excludeIds, parentId);
            Platform.runLater(() -> {
                messageField.setDisable(false);
                messageField.requestFocus();
                if (resultConversationId != null) {
                    onRemoveAttachment();
                    // If this message spawned/landed in a different
                    // conversation (a new or reused restricted thread),
                    // switch straight to it so the sender sees where their
                    // message actually went.
                    loadConversation(resultConversationId == mainConversationId ? -1 : resultConversationId);
                } else if (!NetworkUtil.isNetworkAvailable()) {
                    // Connectivity dropped between the check above and the
                    // request actually landing - still don't lose the draft.
                    if (attachment == null) queueOfflineSend(text, excludeIds);
                } else {
                    messageField.setText(text);
                    Alert alert = new Alert(Alert.AlertType.WARNING,
                        lastSendError != null ? lastSendError : "Couldn't send that message.");
                    alert.setHeaderText(null);
                    alert.showAndWait();
                    lastSendError = null;
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

    private Integer post(String messageBody, List<Integer> excludeIds, Integer parentMessageId) {
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
            if (parentMessageId != null) {
                payload.put("parent_message_id", parentMessageId);
            }
            try (OutputStream os = conn.getOutputStream()) {
                os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
            }

            int code = conn.getResponseCode();
            if (code != 200 && code != 201) {
                lastSendError = errorMessageFrom(conn);
                return null;
            }
            JSONObject response = new JSONObject(readBody(conn));
            return response.getInt("conversation_id");
        } catch (Exception e) {
            System.err.println("[GroupChat] POST error: " + e.getMessage());
            return null;
        }
    }

    /** Multipart counterpart to post() - used whenever an attachment is
     *  selected, since a JSON body can't carry file bytes. Mirrors
     *  TopicController's postReplyMultipart() field-by-field. */
    private Integer postMultipart(String messageBody, List<Integer> excludeIds, File attachment, Integer parentMessageId) {
        String boundary = "----DiscussionHubBoundary" + System.currentTimeMillis();
        try {
            URL url = URI.create(BASE_URL + "/api/groups/" + groupId + "/chat-messages").toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);
            conn.setDoOutput(true);

            try (OutputStream os = conn.getOutputStream()) {
                writeMultipartField(os, boundary, "body", messageBody);
                if (isRestricted) {
                    writeMultipartField(os, boundary, "conversation_id", String.valueOf(activeConversationId));
                } else {
                    for (Integer id : excludeIds) {
                        writeMultipartField(os, boundary, "exclude[]", String.valueOf(id));
                    }
                }
                if (parentMessageId != null) {
                    writeMultipartField(os, boundary, "parent_message_id", String.valueOf(parentMessageId));
                }

                String mimeType = URLConnection.guessContentTypeFromName(attachment.getName());
                if (mimeType == null) mimeType = "application/octet-stream";
                os.write(("--" + boundary + "\r\n").getBytes(StandardCharsets.UTF_8));
                os.write(("Content-Disposition: form-data; name=\"attachment\"; filename=\""
                    + attachment.getName() + "\"\r\n").getBytes(StandardCharsets.UTF_8));
                os.write(("Content-Type: " + mimeType + "\r\n\r\n").getBytes(StandardCharsets.UTF_8));
                Files.copy(attachment.toPath(), os);
                os.write("\r\n".getBytes(StandardCharsets.UTF_8));

                os.write(("--" + boundary + "--\r\n").getBytes(StandardCharsets.UTF_8));
            }

            int code = conn.getResponseCode();
            if (code != 200 && code != 201) {
                lastSendError = errorMessageFrom(conn);
                return null;
            }
            JSONObject response = new JSONObject(readBody(conn));
            return response.getInt("conversation_id");
        } catch (Exception e) {
            System.err.println("[GroupChat] Multipart POST error: " + e.getMessage());
            return null;
        }
    }

    private void writeMultipartField(OutputStream os, String boundary, String name, String value) throws IOException {
        os.write(("--" + boundary + "\r\n").getBytes(StandardCharsets.UTF_8));
        os.write(("Content-Disposition: form-data; name=\"" + name + "\"\r\n\r\n").getBytes(StandardCharsets.UTF_8));
        os.write((value + "\r\n").getBytes(StandardCharsets.UTF_8));
    }

    private String readBody(HttpURLConnection conn) throws Exception {
        BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
        StringBuilder response = new StringBuilder();
        String line;
        while ((line = in.readLine()) != null) response.append(line);
        in.close();
        return response.toString();
    }

    @FXML
    protected void onBack() {
        stopAutoRefresh();
        markCurrentConversationRead();
        if (pusher != null) pusher.disconnect();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) titleLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Desktop Client");
        } catch (Exception e) {
            System.err.println("[GroupChat] Error going back: " + e.getMessage());
        }
    }

}
