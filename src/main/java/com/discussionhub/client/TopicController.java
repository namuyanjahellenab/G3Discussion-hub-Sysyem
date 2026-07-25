package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;
import com.discussionhub.client.utils.AppConfig;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import javafx.animation.Animation;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.stage.FileChooser;
import javafx.stage.Stage;
import javafx.util.Duration;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.*;
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

// Mirrors resources/views/topics/show.blade.php: original post, threaded
// replies (accept/flag/delete based on the same permission rules as the
// web), reply composer with the same spam/relevance moderation gate, real
// PDF export, and the full right panel (profile, Topic Info, Participants,
// Related Topics).
public class TopicController {

    private static final String BASE_URL = AppConfig.BASE_URL;

    @FXML private ScrollPane mainScrollPane;
    @FXML private Label syncStatusLabel;
    @FXML private Label topicTitleLabel;
    @FXML private Label statusBadgeLabel;
    @FXML private Label restrictedBadgeLabel;
    @FXML private Label threadMetaLabel;
    @FXML private VBox mainPostCard;
    @FXML private VBox repliesBox;
    @FXML private HBox replyContextRow;
    @FXML private Label replyContextLabel;
    @FXML private Button cancelReplyButton;
    @FXML private TextArea replyInput;
    @FXML private Label attachedFileLabel;
    @FXML private Button removeAttachmentButton;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private Label infoGroupLabel;
    @FXML private Label infoStatusLabel;
    @FXML private Label infoActivityLabel;
    @FXML private Label participantsHeadingLabel;
    @FXML private FlowPane participantsBox;
    @FXML private VBox relatedTopicsBox;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private int topicId;
    private int groupId;
    private String groupName;
    private Integer mainPostId;
    private Integer replyToId;
    private File selectedAttachment;

    // Tracks exactly what's currently on screen so refresh() can diff against
    // it instead of tearing the whole list down every cycle: an untouched
    // reply's row is never removed or rebuilt, which is what actually stops
    // the "blinks while reading" symptom (restoring scroll position alone
    // only hid the top-jump, the rebuild itself was still a visible reflow).
    private final Map<Integer, HBox> renderedReplyRows = new LinkedHashMap<>();
    private final Map<Integer, String> renderedReplySignatures = new HashMap<>();
    // "created_at" ("3 minutes ago") is deliberately left out of the
    // signature above - it changes every cycle just from time passing, which
    // would defeat the whole point of diffing. But that means it needs its
    // own always-updated path, or the relative time would freeze forever at
    // whatever it said when the row was first drawn.
    private final Map<Integer, Label> renderedReplyTimeLabels = new HashMap<>();
    // Same "don't touch it if nothing changed" idea for the offline/cached
    // path (renderCached()), which - unlike the live path above - doesn't
    // diff row-by-row and just rebuilds repliesBox wholesale. Two
    // consecutive offline polls almost always see identical cached data (the
    // cache doesn't change between polls while offline), so skipping the
    // rebuild entirely on a signature match kills the "blinks and jumps to
    // the top every 10s while offline" symptom at the source.
    private String renderedCachedSignature = null;
    private String renderedMainPostSignature;

    // Local "unread" tracking (see DatabaseManager's ReadState table) - the
    // "NEW MESSAGES" divider sits above every reply with an id greater than
    // unreadThresholdReplyId, which is frozen at whatever it was the moment
    // this topic was opened (not updated live), so the divider doesn't jump
    // around mid-visit as more replies stream in. maxSeenReplyId tracks the
    // highest reply id actually rendered this visit; markCurrentTopicRead()
    // persists it as the new threshold once the user leaves, so the divider
    // is gone next time - "till they are opened and read for it to
    // disappear", not while still on screen.
    private int unreadThresholdReplyId = 0;
    private boolean liveUnreadBannerInserted = false;
    private int maxSeenReplyId = 0;

    // Same whitelist as the web's reply composer (topics/show.blade.php) and
    // AttachmentUploader/TopicApiController's server-side validation - kept
    // in sync by hand since there's no shared config between Java and PHP.
    private static final String[] ATTACHMENT_EXTENSIONS = {
        "*.pdf", "*.doc", "*.docx", "*.ppt", "*.pptx", "*.png", "*.jpg", "*.jpeg", "*.zip"
    };
    private static final long MAX_ATTACHMENT_BYTES = 20L * 1024 * 1024;

    // Replies/flags/accepts made elsewhere (e.g. the web) have no way to
    // reach an already-open desktop screen — this app has no WebSocket or
    // push layer, so we poll instead.
    private static final Duration AUTO_REFRESH_INTERVAL = Duration.seconds(10);
    private Timeline autoRefreshTimeline;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("forum");
        sidebarController.setOnBeforeNavigate(() -> {
            stopAutoRefresh();
            markCurrentTopicRead();
        });
        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
        userRoleLabel.setText((SessionManager.role == null || SessionManager.role.isBlank() ? "Student" : SessionManager.role) + " Account");
    }

    public void loadTopic(String topicTitle, int topicId) {
        markCurrentTopicRead(); // persist read-state for whatever topic was open before this one (e.g. via Related Topics)
        this.topicId = topicId;
        topicTitleLabel.setText(topicTitle);
        renderedReplyRows.clear();
        renderedReplySignatures.clear();
        renderedReplyTimeLabels.clear();
        renderedMainPostSignature = null;
        renderedCachedSignature = null;
        unreadThresholdReplyId = dbManager.getLastReadItemId("Topic", topicId);
        liveUnreadBannerInserted = false;
        maxSeenReplyId = 0;
        // Opportunistic: flushes anything queued (topics/replies composed
        // offline earlier) and pulls fresh Topic/Post/Reply rows into the
        // local cache, so a later offline visit to this same topic has
        // something recent to fall back to instead of a stale/empty cache.
        if (com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable()) {
            new Thread(syncService::synchronizeLocalChanges).start();
        }
        refresh();
        startAutoRefresh();
    }

    /** Persists maxSeenReplyId as the new "last read" marker for whichever
     *  topic is currently open, so the NEW MESSAGES divider and the topic
     *  list's unread badge are both gone next time - not while still on
     *  this screen (see the unreadThresholdReplyId field comment). */
    private void markCurrentTopicRead() {
        if (topicId > 0 && maxSeenReplyId > 0) {
            dbManager.markRead("Topic", topicId, maxSeenReplyId);
        }
    }

    private void startAutoRefresh() {
        if (autoRefreshTimeline == null) {
            autoRefreshTimeline = new Timeline(new KeyFrame(AUTO_REFRESH_INTERVAL, e -> refresh()));
            autoRefreshTimeline.setCycleCount(Animation.INDEFINITE);
        }
        autoRefreshTimeline.playFromStart();
    }

    private void stopAutoRefresh() {
        if (autoRefreshTimeline != null) {
            autoRefreshTimeline.stop();
        }
    }

    private void refresh() {
        new Thread(() -> {
            boolean online = com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable();
            String body = online ? get("/api/topics/" + topicId) : null;

            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    Platform.runLater(() -> {
                        markOnline();
                        // Skip a cycle entirely while they're actively composing, so a
                        // reply never gets rebuilt out from under someone mid-sentence.
                        if (replyInput.isFocused() && !replyInput.getText().isBlank()) {
                            return;
                        }
                        // render() now diffs instead of rebuilding, so untouched rows
                        // never move - but if a new reply appends below the viewport,
                        // the scrollable range still grows. Restoring by vvalue (a
                        // fraction of that range) would drift once the denominator
                        // changes, so convert to/from an absolute pixel offset instead
                        // for an exact match regardless of how much content was added.
                        javafx.scene.Node content = mainScrollPane.getContent();
                        double viewportHeight = mainScrollPane.getViewportBounds().getHeight();
                        double maxScrollBefore = Math.max(1, content.getBoundsInLocal().getHeight() - viewportHeight);
                        double pixelOffset = mainScrollPane.getVvalue() * maxScrollBefore;

                        render(json);

                        Platform.runLater(() -> {
                            double maxScrollAfter = Math.max(1, content.getBoundsInLocal().getHeight() - viewportHeight);
                            mainScrollPane.setVvalue(Math.min(1.0, pixelOffset / maxScrollAfter));
                        });
                    });
                    return;
                } catch (Exception e) {
                    System.err.println("[Topic] Parse error: " + e.getMessage());
                }
            }

            // Offline, or the live call failed anyway - fall back to
            // whatever's cached locally for this topic instead of leaving
            // the screen blank/frozen.
            com.discussionhub.client.database.CachedTopicItem cachedTopic = dbManager.getCachedTopicById(topicId);
            List<com.discussionhub.client.database.CachedPostItem> cachedPosts = dbManager.getCachedPosts(topicId);
            com.discussionhub.client.database.CachedPostItem mainPost = cachedPosts.isEmpty() ? null : cachedPosts.get(0);
            List<com.discussionhub.client.database.CachedReplyItem> cachedReplies =
                mainPost != null ? dbManager.getCachedReplies(mainPost.getPostId()) : new ArrayList<>();
            Platform.runLater(() -> {
                markOffline();
                // Same pixel-offset scroll preservation as the online path
                // above - belt-and-suspenders alongside renderCached()'s own
                // signature check, in case the cache genuinely did change
                // (e.g. a reply synced in) and the rebuild isn't skipped.
                javafx.scene.Node content = mainScrollPane.getContent();
                double viewportHeight = mainScrollPane.getViewportBounds().getHeight();
                double maxScrollBefore = Math.max(1, content.getBoundsInLocal().getHeight() - viewportHeight);
                double pixelOffset = mainScrollPane.getVvalue() * maxScrollBefore;

                renderCached(cachedTopic, mainPost, cachedReplies);

                Platform.runLater(() -> {
                    double maxScrollAfter = Math.max(1, content.getBoundsInLocal().getHeight() - viewportHeight);
                    mainScrollPane.setVvalue(Math.min(1.0, pixelOffset / maxScrollAfter));
                });
            });
        }).start();
    }

    private void markOnline() {
        syncStatusLabel.setText("● ONLINE");
        syncStatusLabel.setStyle("-fx-text-fill: #3F9C6B; -fx-font-size: 12; -fx-font-weight: bold;");
    }

    private void markOffline() {
        syncStatusLabel.setText("● OFFLINE — showing saved discussion");
        syncStatusLabel.setStyle("-fx-text-fill: #D9483D; -fx-font-size: 12; -fx-font-weight: bold;");
    }

    /** Offline is not a degraded "form" of online - it's the SAME bubble
     *  layout (buildMainPostBubble/buildReplyBubble), just fed from
     *  CachedPostItem/CachedReplyItem instead of a live JSON response.
     *  Moderation actions (accept/flag/delete) are simply omitted rather
     *  than shown-and-broken, since those need a live round-trip anyway. */
    private void renderCached(com.discussionhub.client.database.CachedTopicItem cachedTopic,
                               com.discussionhub.client.database.CachedPostItem mainPost,
                               List<com.discussionhub.client.database.CachedReplyItem> replies) {
        // Recovers Group Info + where "Back" goes on a cold offline start -
        // a topic reached straight from a cached group topic list was never
        // loaded through the live /api/topics/{id} response that would
        // normally set groupId/groupName/infoGroupLabel.
        if (cachedTopic != null) {
            groupId = cachedTopic.getGroupId();
            groupName = cachedTopic.getCategory();
            infoGroupLabel.setText(groupName);
        }

        if (mainPost == null) {
            mainPostCard.getChildren().setAll(emptyState("No saved content for this topic yet — connect once to cache it for offline use."));
            repliesBox.getChildren().clear();
            mainPostId = null;
            return;
        }

        mainPostId = mainPost.getPostId();
        String mainAuthorName = resolveAuthorName(mainPost.getUserId(), mainPost.getAuthorName());

        // Identical to what's already rendered (the overwhelmingly common
        // case for back-to-back offline polls, since the cache doesn't
        // change between them) - skip the rebuild entirely instead of
        // tearing repliesBox down and redrawing the exact same thing, which
        // is what caused the visible blink and reset the scroll to the top.
        String signature = mainPost.getPostId() + "|" + mainPost.getContent() + "|" + mainPost.isPending() + "|"
            + replies.size() + "|" + (replies.isEmpty() ? 0 : replies.get(replies.size() - 1).getReplyId());
        if (signature.equals(renderedCachedSignature)) {
            return;
        }
        renderedCachedSignature = signature;

        threadMetaLabel.setText("Posted by " + mainAuthorName);
        mainPostCard.getChildren().setAll(buildMainPostBubble(mainAuthorName, mainPost.getContent(), mainPost.isPending()));

        repliesBox.getChildren().clear();
        if (replies.isEmpty()) {
            repliesBox.getChildren().add(emptyState("No saved replies yet."));
        } else {
            // A one-shot full rebuild every call (unlike the live path's
            // diffing), so this flag is local instead of the shared
            // liveUnreadBannerInserted field - it must reset every render.
            boolean bannerInserted = false;
            for (com.discussionhub.client.database.CachedReplyItem r : replies) {
                if (r.getReplyId() > maxSeenReplyId) maxSeenReplyId = r.getReplyId();
                boolean isOwn = r.getUserId() == SessionManager.userId;
                // Receiver-only, WhatsApp-style: your own replies never
                // trigger the divider, since you already know you sent them.
                if (!bannerInserted && !isOwn && unreadThresholdReplyId > 0 && r.getReplyId() > unreadThresholdReplyId) {
                    repliesBox.getChildren().add(buildUnreadBanner());
                    bannerInserted = true;
                }
                HBox replyRow = buildReplyBubble(
                    r.getReplyId(), r.getContent(), resolveAuthorName(r.getUserId(), r.getAuthorName()), isOwn,
                    TextUtil.timeAgo(r.getCreatedAt()), false, false, false,
                    false, false, false, false, false,
                    null, null,
                    r.getAttachmentUrl(), r.getAttachmentType(), r.getAttachmentName(),
                    r.isPending(), null
                );
                repliesBox.getChildren().add(replyRow);
            }
        }
    }

    /** Real name (or "Member #N") - never "You", since buildReplyBubble/
     *  buildMainPostBubble already do that substitution themselves where
     *  it's wanted, exactly mirroring what the live JSON path does (the
     *  server never sends "You" either - it always sends the real name and
     *  lets the client decide based on is_own). */
    private String resolveAuthorName(int userId, String authorName) {
        if (authorName != null && !authorName.isBlank()) return authorName;
        return "Member #" + userId;
    }

    /** WhatsApp-style "── NEW MESSAGES ──" divider (WhatsApp green, matching
     *  its own unread marker), shown once above the first RECEIVED reply
     *  newer than the frozen read marker for this visit - never for the
     *  viewer's own replies (see the isOwn check at both call sites). */
    private HBox buildUnreadBanner() {
        Region leftLine = new Region();
        leftLine.setStyle("-fx-background-color: -accent-success; -fx-pref-height: 1;");
        HBox.setHgrow(leftLine, Priority.ALWAYS);
        Region rightLine = new Region();
        rightLine.setStyle("-fx-background-color: -accent-success; -fx-pref-height: 1;");
        HBox.setHgrow(rightLine, Priority.ALWAYS);

        Label label = new Label("NEW MESSAGES");
        label.setStyle("-fx-text-fill: -accent-success; -fx-font-size: 10.5; -fx-font-weight: bold; -fx-padding: 0 10;");

        HBox banner = new HBox(0, leftLine, label, rightLine);
        banner.setAlignment(Pos.CENTER);
        banner.setStyle("-fx-padding: 10 4;");
        return banner;
    }

    private Label pendingTag() {
        Label tag = new Label("⏳ Pending — will send when online");
        tag.setStyle("-fx-text-fill: #D98E3D; -fx-font-size: 10.5; -fx-font-weight: bold;");
        return tag;
    }

    private Label emptyState(String text) {
        Label l = new Label(text);
        l.getStyleClass().add("muted-text");
        l.setWrapText(true);
        l.setStyle("-fx-padding: 16 0;");
        return l;
    }

    private void render(JSONObject json) {
        JSONObject topic = json.getJSONObject("topic");
        topicTitleLabel.setText(topic.getString("title"));
        statusBadgeLabel.setText(topic.getString("status"));
        boolean restricted = topic.getBoolean("is_restricted");
        restrictedBadgeLabel.setVisible(restricted);
        restrictedBadgeLabel.setManaged(restricted);

        groupId = topic.getInt("group_id");
        groupName = topic.getString("group_name");
        infoGroupLabel.setText(groupName);
        infoStatusLabel.setText(topic.getString("status"));
        infoActivityLabel.setText(topic.getString("last_activity"));

        if (json.isNull("main_post")) {
            mainPostId = null;
            if (renderedMainPostSignature != null) {
                renderedMainPostSignature = null;
                mainPostCard.getChildren().clear();
                Label empty = new Label("No discussion started in this topic yet.");
                empty.getStyleClass().add("muted-text");
                mainPostCard.getChildren().add(empty);
            }
            renderReplies(new JSONArray());
        } else {
            JSONObject mainPost = json.getJSONObject("main_post");
            mainPostId = mainPost.getInt("id");
            threadMetaLabel.setText("Posted by " + mainPost.getString("author_name") + " • " + mainPost.getString("created_at"));

            String mainSignature = mainPostId + "|" + mainPost.getString("content");
            if (!mainSignature.equals(renderedMainPostSignature)) {
                renderedMainPostSignature = mainSignature;
                mainPostCard.getChildren().setAll(buildMainPostRow(mainPost));
            }

            renderReplies(json.getJSONArray("replies"));
        }

        JSONArray participants = json.getJSONArray("participants");
        participantsHeadingLabel.setText("Participants (" + participants.length() + ")");
        participantsBox.getChildren().clear();
        for (int i = 0; i < participants.length(); i++) {
            String pname = participants.getJSONObject(i).getString("name");
            Label avatar = new Label(TextUtil.initials(pname));
            avatar.setStyle("-fx-background-color: #26658C; -fx-text-fill: white; -fx-font-weight: bold; " +
                "-fx-padding: 6 9; -fx-background-radius: 14; -fx-font-size: 11;");
            participantsBox.getChildren().add(avatar);
        }

        JSONArray related = json.getJSONArray("recommended");
        relatedTopicsBox.getChildren().clear();
        if (related.isEmpty()) {
            Label empty = new Label("No other topics in this group yet.");
            empty.getStyleClass().add("muted-text");
            empty.setStyle("-fx-padding: 16 20;");
            relatedTopicsBox.getChildren().add(empty);
        } else {
            for (int i = 0; i < related.length(); i++) {
                JSONObject rec = related.getJSONObject(i);
                VBox row = new VBox(2);
                row.setStyle("-fx-padding: 10 20; -fx-border-color: transparent transparent #E1E9ED transparent; -fx-border-width: 0 0 1 0; -fx-cursor: hand;");
                Label title = new Label(rec.getString("title"));
                title.getStyleClass().add("heading-text");
                title.setStyle("-fx-font-size: 12.5;");
                int postsCount = rec.optInt("posts_count", 0);
                Label meta = new Label(postsCount + " " + (postsCount == 1 ? "reply" : "replies") + " • " + rec.optString("created_at", ""));
                meta.getStyleClass().add("muted-text");
                row.getChildren().addAll(title, meta);
                row.setOnMouseClicked(e -> loadTopic(rec.getString("title"), rec.getInt("id")));
                relatedTopicsBox.getChildren().add(row);
            }
        }
    }

    private HBox buildMainPostRow(JSONObject mainPost) {
        return buildMainPostBubble(mainPost.getString("author_name"), mainPost.getString("content"), false);
    }

    /** Shared by the live and cached/offline paths - see renderCached(). */
    private HBox buildMainPostBubble(String authorName, String content, boolean pending) {
        Label mainAvatar = new Label(TextUtil.initials(authorName));
        mainAvatar.setMinSize(36, 36);
        mainAvatar.setMaxSize(36, 36);
        mainAvatar.setAlignment(Pos.CENTER);
        mainAvatar.setStyle("-fx-background-color: -luna-mid; -fx-text-fill: white; -fx-font-weight: bold; -fx-font-size: 13; -fx-background-radius: 18;");

        Label author = new Label(authorName);
        author.getStyleClass().add("heading-text");

        Label contentLabel = new Label(content);
        contentLabel.setWrapText(true);
        contentLabel.setStyle("-fx-font-size: 13; -fx-text-fill: -text-body;");

        VBox authorTextColumn = new VBox(6);
        if (pending) authorTextColumn.getChildren().add(pendingTag());
        authorTextColumn.getChildren().addAll(author, contentLabel);
        HBox.setHgrow(authorTextColumn, Priority.ALWAYS);

        HBox authorRow = new HBox(12, mainAvatar, authorTextColumn);
        authorRow.setAlignment(Pos.TOP_LEFT);
        return authorRow;
    }

    /**
     * Diffs the incoming reply list against what's already rendered instead
     * of clearing and rebuilding repliesBox every refresh cycle: a reply
     * whose signature hasn't changed is left completely alone (no remove, no
     * re-add), so there's nothing for the user to see blink whether they're
     * typing or just reading. Replies only ever append at the end (ReplyID
     * is auto-increment with no re-ordering in the API response), so a
     * never-seen id can safely be added at the end without recomputing
     * everyone else's position.
     */
    private void renderReplies(JSONArray replies) {
        Set<Integer> incomingIds = new LinkedHashSet<>();
        for (int i = 0; i < replies.length(); i++) {
            incomingIds.add(replies.getJSONObject(i).getInt("id"));
        }

        Iterator<Map.Entry<Integer, HBox>> it = renderedReplyRows.entrySet().iterator();
        while (it.hasNext()) {
            Map.Entry<Integer, HBox> entry = it.next();
            if (!incomingIds.contains(entry.getKey())) {
                repliesBox.getChildren().remove(entry.getValue());
                renderedReplySignatures.remove(entry.getKey());
                renderedReplyTimeLabels.remove(entry.getKey());
                it.remove();
            }
        }

        if (!replies.isEmpty() && renderedReplyRows.isEmpty() && !repliesBox.getChildren().isEmpty()) {
            repliesBox.getChildren().clear();
        }

        for (int i = 0; i < replies.length(); i++) {
            JSONObject reply = replies.getJSONObject(i);
            int id = reply.getInt("id");
            if (id > maxSeenReplyId) maxSeenReplyId = id;
            String signature = replySignature(reply);

            // Persists every reply this device ever sees live (not just ones
            // pulled by a full sync) - the periodic 10s poll used to only
            // render straight from the JSON response without writing it to
            // the local cache, so a reply that arrived while this screen was
            // already open would show up live but vanish the moment the
            // screen fell back to offline rendering, having never actually
            // been saved. Idempotent (INSERT OR REPLACE), so re-running it
            // every refresh cycle for unchanged replies is harmless.
            dbManager.mergeReply(
                id, mainPostId, reply.getInt("user_id"), reply.getString("content"),
                reply.getString("created_at_iso"), reply.getString("author_name"),
                reply.isNull("attachment_url") ? null : reply.getString("attachment_url"),
                reply.optString("attachment_type", null),
                reply.optString("attachment_name", null)
            );

            HBox existingRow = renderedReplyRows.get(id);

            // Keep the relative timestamp ticking even for an otherwise
            // untouched row - this is the only part of an unchanged reply
            // that's allowed to update in place, everything else stays put.
            Label existingTimeLabel = renderedReplyTimeLabels.get(id);
            if (existingTimeLabel != null) {
                existingTimeLabel.setText(reply.getString("created_at"));
            }

            if (existingRow != null && signature.equals(renderedReplySignatures.get(id))) {
                continue;
            }

            // Inserted once per topic-open, right before the first RECEIVED
            // reply (never the viewer's own) that's newer than the frozen
            // read marker - later refresh cycles skip this
            // (liveUnreadBannerInserted already true), so it never moves or
            // duplicates mid-visit.
            if (!liveUnreadBannerInserted && !reply.getBoolean("is_own") && unreadThresholdReplyId > 0 && id > unreadThresholdReplyId) {
                repliesBox.getChildren().add(buildUnreadBanner());
                liveUnreadBannerInserted = true;
            }

            HBox newRow = buildReplyRow(reply);
            if (existingRow != null) {
                int index = repliesBox.getChildren().indexOf(existingRow);
                if (index >= 0) {
                    repliesBox.getChildren().set(index, newRow);
                } else {
                    repliesBox.getChildren().add(newRow);
                }
            } else {
                repliesBox.getChildren().add(newRow);
            }
            renderedReplyRows.put(id, newRow);
            renderedReplySignatures.put(id, signature);
        }

        if (replies.isEmpty() && repliesBox.getChildren().isEmpty()) {
            Label empty = new Label("No replies yet. Be the first to respond.");
            empty.getStyleClass().add("muted-text");
            repliesBox.getChildren().add(empty);
        }
    }

    private String replySignature(JSONObject reply) {
        return reply.getString("content") + "|" + reply.getBoolean("is_accepted") + "|" + reply.getBoolean("is_flagged")
            + "|" + reply.getBoolean("can_flag") + "|" + reply.optBoolean("has_flagged", false)
            + "|" + reply.getBoolean("can_accept") + "|" + reply.getBoolean("can_delete");
    }

    /**
     * WhatsApp/Telegram-style bubble: the current user's own replies sit on
     * the right in the theme's accent color, everyone else's sit on the left
     * in a plain card - mirrors the same is_own split as the web version's
     * topics/show.blade.php (see .reply-row.own / .reply-row.other there).
     */
    private HBox buildReplyRow(JSONObject reply) {
        int id = reply.getInt("id");
        return buildReplyBubble(
            id, reply.getString("content"), reply.getString("author_name"), reply.getBoolean("is_own"),
            reply.getString("created_at"), reply.getBoolean("is_accepted"), reply.getBoolean("is_flagged"),
            reply.getBoolean("is_lecturer"), reply.getBoolean("can_flag"), reply.optBoolean("has_flagged", false),
            reply.getBoolean("can_accept"), reply.getBoolean("can_delete"), true,
            (!reply.isNull("parent_reply_author")) ? reply.getString("parent_reply_author") : null,
            reply.optString("parent_reply_snippet", ""),
            reply.isNull("attachment_url") ? null : reply.getString("attachment_url"),
            reply.optString("attachment_type", "file"),
            reply.optString("attachment_name", "attachment"),
            false,
            time -> renderedReplyTimeLabels.put(id, time)
        );
    }

    /** Shared by the live (JSON) and cached/offline (CachedReplyItem) reply
     *  lists - same avatars, bubble colors/alignment, attachment handling,
     *  everywhere. canFlag/canAccept/canDelete/canReply are all forced off
     *  for cached rows since moderation actions need a live round-trip;
     *  timeLabelConsumer lets the live path's diff/refresh logic capture the
     *  timestamp Label to tick in place, and is null for the one-shot
     *  offline render. */
    private HBox buildReplyBubble(int replyId, String content, String authorName, boolean isOwn,
                                   String timestampText, boolean isAccepted, boolean isFlagged, boolean isLecturer,
                                   boolean canFlag, boolean hasFlagged, boolean canAccept, boolean canDelete, boolean canReply,
                                   String parentReplyAuthor, String parentReplySnippet,
                                   String attachmentUrl, String attachmentType, String attachmentName,
                                   boolean pending, java.util.function.Consumer<Label> timeLabelConsumer) {
        boolean special = isAccepted || isFlagged;
        String textColor = (isOwn && !special) ? "white" : "-text-body";

        Label avatar = new Label(TextUtil.initials(authorName));
        avatar.setMinSize(32, 32);
        avatar.setMaxSize(32, 32);
        avatar.setAlignment(Pos.CENTER);
        avatar.setStyle("-fx-background-color: " + (isLecturer ? "-accent-success" : "-luna-mid") +
            "; -fx-text-fill: white; -fx-font-weight: bold; -fx-font-size: 12; -fx-background-radius: 16;");

        HBox top = new HBox(6);
        top.setAlignment(Pos.CENTER_LEFT);
        Label author = new Label(isOwn ? "You" : authorName);
        author.getStyleClass().add("heading-text");
        author.setStyle("-fx-font-size: 12;");
        Label time = new Label(timestampText);
        time.getStyleClass().add("muted-text");
        time.setStyle("-fx-font-size: 10.5;");
        if (timeLabelConsumer != null) timeLabelConsumer.accept(time);
        top.getChildren().addAll(author, time);
        if (canFlag) {
            // Same icon either way (matches the web's single flag glyph,
            // just re-colored) - the action and tooltip are what actually
            // change between "report" and "withdraw my report".
            Button flagBtn = hasFlagged
                ? actionButton("🚩", () -> unflagReply(replyId))
                : actionButton("🚩", () -> flagReply(replyId));
            flagBtn.setTooltip(new Tooltip(hasFlagged ? "Withdraw report" : "Report"));
            if (hasFlagged) flagBtn.setStyle(flagBtn.getStyle() + " -fx-text-fill: -accent-danger;");
            top.getChildren().add(flagBtn);
        }
        if (canReply) top.getChildren().add(actionButton("↩", () -> startReplyTo(replyId, authorName)));
        if (canAccept) top.getChildren().add(actionButton("✔", () -> acceptAnswer(replyId)));
        if (canDelete) top.getChildren().add(actionButton("🗑", () -> deleteReply(replyId)));

        VBox bubble = new VBox(6);
        bubble.setMaxWidth(420);
        String bg = isAccepted ? "-accent-success-bg" : isFlagged ? "-accent-danger-bg" : (isOwn ? "-luna-mid" : "-surface-card");
        String border = isAccepted ? "-fx-border-color: -accent-success; -fx-border-width: 2;"
            : isFlagged ? "-fx-border-color: -accent-danger; -fx-border-width: 1;"
            : (!isOwn ? "-fx-border-color: -surface-border; -fx-border-width: 1;" : "");
        String radius = isOwn ? "-fx-background-radius: 14 4 14 14; -fx-border-radius: 14 4 14 14;"
            : "-fx-background-radius: 4 14 14 14; -fx-border-radius: 4 14 14 14;";
        bubble.setStyle("-fx-background-color: " + bg + "; " + radius + " " + border + " -fx-padding: 10 14;");

        if (pending) bubble.getChildren().add(pendingTag());

        if (parentReplyAuthor != null) {
            Label quote = new Label("↩ Replying to " + parentReplyAuthor + ": " + (parentReplySnippet != null ? parentReplySnippet : ""));
            quote.setWrapText(true);
            quote.setStyle("-fx-background-color: -luna-lightest; -fx-text-fill: -luna-dark; -fx-padding: 3 8; -fx-background-radius: 6; -fx-font-size: 11;");
            bubble.getChildren().add(quote);
        }
        if (isAccepted) {
            Label acceptedTag = new Label("✔ Marked as answer");
            acceptedTag.setStyle("-fx-text-fill: -accent-success; -fx-font-weight: bold; -fx-font-size: 11;");
            bubble.getChildren().add(acceptedTag);
        }
        if (isFlagged) {
            Label flaggedTag = new Label("🚩 Flagged");
            flaggedTag.setStyle("-fx-text-fill: -accent-danger; -fx-font-weight: bold; -fx-font-size: 11;");
            bubble.getChildren().add(flaggedTag);
        }

        Label contentLabel = new Label(content);
        contentLabel.setWrapText(true);
        contentLabel.setMaxWidth(380);
        contentLabel.setStyle("-fx-font-size: 13; -fx-text-fill: " + textColor + ";");
        bubble.getChildren().add(contentLabel);

        if (attachmentUrl != null) {
            bubble.getChildren().add(buildAttachmentNode(
                attachmentUrl,
                attachmentType != null ? attachmentType : "file",
                attachmentName != null ? attachmentName : "attachment"
            ));
        }

        VBox bubbleColumn = new VBox(3, top, bubble);
        bubbleColumn.setFillWidth(false);
        bubbleColumn.setAlignment(isOwn ? Pos.CENTER_RIGHT : Pos.CENTER_LEFT);

        HBox row = new HBox(8);
        row.setMaxWidth(Double.MAX_VALUE);
        row.setAlignment(isOwn ? Pos.CENTER_RIGHT : Pos.CENTER_LEFT);
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        if (isOwn) {
            row.getChildren().addAll(spacer, bubbleColumn, avatar);
        } else {
            row.getChildren().addAll(avatar, bubbleColumn, spacer);
        }
        return row;
    }

    /** Attachments already viewed once while online get downloaded into
     *  AttachmentCache in the background so they stay visible/openable
     *  offline too - "everyone can see their attachments" per the WhatsApp-
     *  parity requirement, not just the text around them. If we're offline
     *  and it was never cached, show a plain notice instead of a broken
     *  image / dead link. */
    private javafx.scene.Node buildAttachmentNode(String url, String type, String name) {
        String fullUrl = url.startsWith("http") ? url : BASE_URL + url;
        boolean online = com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable();
        File cachedFile = com.discussionhub.client.utils.AttachmentCache.localFileFor(fullUrl);
        boolean isCached = cachedFile.isFile();

        if (online) {
            com.discussionhub.client.utils.AttachmentCache.ensureCachedAsync(fullUrl);
        }

        if (!online && !isCached) {
            Label unavailable = new Label("📎 " + name + " (unavailable offline — open it once while connected to save it)");
            unavailable.getStyleClass().add("muted-text");
            unavailable.setWrapText(true);
            unavailable.setStyle("-fx-font-size: 11.5; -fx-font-style: italic;");
            return unavailable;
        }

        if ("image".equals(type)) {
            // Capped height, not just width - a tall/portrait screenshot
            // used to render at full native height with nothing limiting
            // it, so one reply with an image attached could fill the whole
            // visible replies area on its own. preserveRatio keeps it from
            // distorting; clicking still opens the untouched full-size
            // original via openAttachment().
            String imageSource;
            if (isCached) {
                imageSource = cachedFile.toURI().toString();
            } else {
                String encoded;
                try {
                    encoded = com.discussionhub.client.utils.AttachmentCache.toSafeUri(fullUrl).toString();
                } catch (Exception e) {
                    encoded = fullUrl;
                }
                imageSource = encoded;
            }
            ImageView imageView = new ImageView(new Image(imageSource, 260, 220, true, true, true));
            imageView.setPreserveRatio(true);
            imageView.setFitWidth(260);
            imageView.setFitHeight(220);
            imageView.setStyle("-fx-cursor: hand;");
            imageView.setOnMouseClicked(e -> openAttachment(fullUrl, cachedFile));

            Button downloadBtn = new Button("⬇ Save");
            downloadBtn.setStyle("-fx-background-color: #F4F8FA; -fx-text-fill: #33455A; -fx-font-size: 10.5; "
                + "-fx-padding: 3 8; -fx-background-radius: 6; -fx-cursor: hand;");
            downloadBtn.setOnAction(e -> downloadAttachment(fullUrl, cachedFile, name));

            VBox wrapper = new VBox(4, imageView, downloadBtn);
            VBox.setMargin(wrapper, new Insets(4, 0, 0, 0));
            return wrapper;
        }

        Label fileLink = new Label("📎 " + name);
        fileLink.setStyle("-fx-text-fill: #26658C; -fx-font-weight: bold; -fx-font-size: 12; -fx-cursor: hand; -fx-underline: true;");
        fileLink.setOnMouseClicked(e -> openAttachment(fullUrl, cachedFile));

        Button downloadBtn = new Button("⬇");
        downloadBtn.setStyle("-fx-background-color: transparent; -fx-text-fill: #6B8094; -fx-font-size: 12; -fx-padding: 0 0 0 6; -fx-cursor: hand;");
        downloadBtn.setOnAction(e -> downloadAttachment(fullUrl, cachedFile, name));

        HBox fileRow = new HBox(fileLink, downloadBtn);
        fileRow.setAlignment(Pos.CENTER_LEFT);
        return fileRow;
    }

    /** Small, simple "Save As" for an attachment - copies the already-cached
     *  local file if there is one (the common case, since attachments get
     *  cached in the background as soon as they're viewed), otherwise fetches
     *  it fresh. Mirrors onExportPdf()'s FileChooser pattern. */
    private void downloadAttachment(String fullUrl, File cachedFile, String suggestedName) {
        new Thread(() -> {
            byte[] bytes = null;
            if (cachedFile.isFile()) {
                try {
                    bytes = Files.readAllBytes(cachedFile.toPath());
                } catch (IOException e) {
                    System.err.println("[Topic] Couldn't read cached attachment: " + e.getMessage());
                }
            }
            if (bytes == null) {
                try {
                    HttpURLConnection conn = (HttpURLConnection) com.discussionhub.client.utils.AttachmentCache.toSafeUri(fullUrl).toURL().openConnection();
                    conn.setRequestMethod("GET");
                    if (conn.getResponseCode() == 200) {
                        bytes = conn.getInputStream().readAllBytes();
                    }
                } catch (Exception e) {
                    System.err.println("[Topic] Couldn't download attachment: " + e.getMessage());
                }
            }
            final byte[] finalBytes = bytes;
            Platform.runLater(() -> {
                if (finalBytes == null) {
                    Alert alert = new Alert(Alert.AlertType.ERROR, "Couldn't download this attachment — check your connection.");
                    alert.showAndWait();
                    return;
                }
                FileChooser chooser = new FileChooser();
                chooser.setInitialFileName(suggestedName);
                File file = chooser.showSaveDialog(topicTitleLabel.getScene().getWindow());
                if (file != null) {
                    try {
                        Files.write(file.toPath(), finalBytes);
                    } catch (IOException e) {
                        System.err.println("[Topic] Error saving attachment: " + e.getMessage());
                    }
                }
            });
        }).start();
    }

    private void openAttachment(String fullUrl, File cachedFile) {
        try {
            if (cachedFile.isFile()) {
                java.awt.Desktop.getDesktop().open(cachedFile);
            } else {
                java.awt.Desktop.getDesktop().browse(com.discussionhub.client.utils.AttachmentCache.toSafeUri(fullUrl));
            }
        } catch (Exception e) {
            System.err.println("[Topic] Couldn't open attachment: " + e.getMessage());
        }
    }

    private Button actionButton(String icon, Runnable action) {
        Button b = new Button(icon);
        b.setStyle("-fx-background-color: transparent; -fx-padding: 2 6; -fx-font-size: 11;");
        b.setOnAction(e -> action.run());
        return b;
    }

    private void startReplyTo(int replyId, String authorName) {
        replyToId = replyId;
        replyContextLabel.setText("↩ Replying to " + authorName);
        replyContextRow.setVisible(true);
        replyContextRow.setManaged(true);
        replyInput.requestFocus();
    }

    @FXML
    protected void onCancelReply() {
        replyToId = null;
        replyContextRow.setVisible(false);
        replyContextRow.setManaged(false);
    }

    @FXML
    protected void onAttachFile() {
        FileChooser chooser = new FileChooser();
        chooser.setTitle("Attach a file");
        chooser.getExtensionFilters().add(new FileChooser.ExtensionFilter(
            "Supported files", ATTACHMENT_EXTENSIONS));
        File file = chooser.showOpenDialog(replyInput.getScene().getWindow());
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
    protected void onPostReply() {
        String content = replyInput.getText().trim();
        if (content.isEmpty() || mainPostId == null) return;
        replyInput.setDisable(true);

        File attachment = selectedAttachment;
        Integer parentReplyId = replyToId;
        int postId = mainPostId;

        new Thread(() -> {
            // A reply typed offline used to just fail outright - queue it
            // the same way an offline chat message already does
            // (queuePendingMessage) instead of discarding what was written.
            // Attachments aren't supported offline (postReplyMultipart() is
            // the only path that handles those, and only when online).
            if (!com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable()) {
                String authorName = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
                    ? SessionManager.userEmail : SessionManager.fullName;
                dbManager.queuePendingReply(postId, SessionManager.userId, content, authorName);
                Platform.runLater(() -> {
                    replyInput.setDisable(false);
                    replyInput.clear();
                    onCancelReply();
                    onRemoveAttachment();
                    refresh();
                });
                return;
            }

            String error = postReplyMultipart(content, parentReplyId, attachment);
            Platform.runLater(() -> {
                replyInput.setDisable(false);
                if (error != null) {
                    Alert alert = new Alert(Alert.AlertType.WARNING, error);
                    alert.setHeaderText(null);
                    alert.showAndWait();
                } else {
                    replyInput.clear();
                    onCancelReply();
                    onRemoveAttachment();
                    refresh();
                }
            });
        }).start();
    }

    private void flagReply(int replyId) {
        // Reason is optional server-side (Reply::flagBy accepts null), but
        // the field exists specifically so a moderator reviewing the queue
        // has some context beyond "someone flagged this" - worth actually
        // asking for it instead of always sending null. showAndWait() is
        // safe to call synchronously here since this runs on the FX
        // Application Thread already (invoked from a button's onAction).
        TextInputDialog reasonDialog = new TextInputDialog();
        reasonDialog.setHeaderText("Why are you reporting this reply?");
        reasonDialog.setContentText("Reason (optional):");
        String reason = reasonDialog.showAndWait().map(String::trim).filter(s -> !s.isEmpty()).orElse(null);

        new Thread(() -> {
            String error = postJson("/api/replies/" + replyId + "/flag", "Reason", reason);
            Platform.runLater(() -> {
                // Flagging needs its own explicit confirmation - unlike
                // delete (row disappears) or accept (the "Marked as answer"
                // tag appears immediately), a single flag usually changes
                // nothing visible at all: IsFlagged only flips once a
                // SECOND, different person also flags it (see the web's
                // Reply::flagBy escalation threshold). Without this, the
                // Report button looked broken - it wasn't, there was just
                // no feedback that the click had registered, success or
                // failure (the error return value was previously discarded
                // outright).
                if (error == null) {
                    new Alert(Alert.AlertType.INFORMATION, "Reply reported. A moderator will review it.").showAndWait();
                } else {
                    new Alert(Alert.AlertType.ERROR, error).showAndWait();
                }
                refresh();
            });
        }).start();
    }

    /** Counterpart to flagReply() - "I reported that by mistake". */
    private void unflagReply(int replyId) {
        new Thread(() -> {
            String error = deleteWithResult("/api/replies/" + replyId + "/flag");
            Platform.runLater(() -> {
                if (error == null) {
                    new Alert(Alert.AlertType.INFORMATION, "Report withdrawn.").showAndWait();
                } else {
                    new Alert(Alert.AlertType.ERROR, error).showAndWait();
                }
                refresh();
            });
        }).start();
    }

    private void acceptAnswer(int replyId) {
        new Thread(() -> {
            postJson("/api/replies/" + replyId + "/accept");
            Platform.runLater(this::refresh);
        }).start();
    }

    private void deleteReply(int replyId) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION, "Delete this reply?");
        confirm.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                new Thread(() -> {
                    delete("/api/replies/" + replyId);
                    Platform.runLater(this::refresh);
                }).start();
            }
        });
    }

    /** Mirrors the web's Share dropdown: WhatsApp/Twitter/Facebook/Email links + Copy Link. */
    @FXML
    protected void onShare() {
        new Thread(() -> {
            String body = get("/api/topics/" + topicId + "/share-links");
            if (body == null) {
                Platform.runLater(() -> {
                    Alert alert = new Alert(Alert.AlertType.ERROR, "Couldn't build share links — check your connection.");
                    alert.showAndWait();
                });
                return;
            }
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> showShareDialog(json));
            } catch (Exception e) {
                System.err.println("[Topic] Share parse error: " + e.getMessage());
            }
        }).start();
    }

    private void showShareDialog(JSONObject shareLinks) {
        Stage dialog = new Stage();
        dialog.initOwner(topicTitleLabel.getScene().getWindow());
        dialog.initModality(javafx.stage.Modality.APPLICATION_MODAL);
        dialog.setTitle("Share Post");

        JSONObject links = shareLinks.getJSONObject("Links");
        String shareUrl = shareLinks.getString("ShareUrl");

        VBox content = new VBox(12);
        content.setStyle("-fx-padding: 24; -fx-background-color: white;");

        Label preview = new Label(shareLinks.optString("ShareText", topicTitleLabel.getText()));
        preview.setWrapText(true);
        preview.getStyleClass().add("muted-text");

        Button whatsapp = shareLink("💬 WhatsApp", links.getString("whatsapp"));
        Button twitter = shareLink("𝕏 Twitter / X", links.getString("twitter"));
        Button facebook = shareLink("📘 Facebook", links.getString("facebook"));
        Button email = emailShareButton("✉ Email", links.getString("email"));

        Button copyLink = new Button("🔗 Copy Link");
        copyLink.setMaxWidth(Double.MAX_VALUE);
        copyLink.setOnAction(e -> {
            javafx.scene.input.ClipboardContent clipboardContent = new javafx.scene.input.ClipboardContent();
            clipboardContent.putString(shareUrl);
            javafx.scene.input.Clipboard.getSystemClipboard().setContent(clipboardContent);
            copyLink.setText("✔ Copied!");
        });

        Button close = new Button("Close");
        close.setMaxWidth(Double.MAX_VALUE);
        close.setOnAction(e -> dialog.close());

        content.getChildren().addAll(
            new Label("Share this topic") {{ getStyleClass().add("heading-text"); setStyle("-fx-font-size: 16;"); }},
            preview, whatsapp, twitter, facebook, email, copyLink, close
        );
        content.setPrefWidth(320);

        dialog.setScene(new Scene(content));
        dialog.show();
    }

    private Button shareLink(String label, String url) {
        Button b = new Button(label);
        b.getStyleClass().add("btn-primary");
        b.setMaxWidth(Double.MAX_VALUE);
        b.setOnAction(e -> {
            try {
                java.awt.Desktop.getDesktop().browse(URI.create(url));
            } catch (Exception ex) {
                System.err.println("[Topic] Couldn't open share link: " + ex.getMessage());
            }
        });
        return b;
    }

    /** Email needs Desktop.Action.MAIL (the actual mailto: handler), not
     *  browse() - browse() only happens to work for mailto: links on Windows
     *  because ShellExecute dispatches by URI scheme regardless, which isn't
     *  a guarantee on other platforms. */
    private Button emailShareButton(String label, String url) {
        Button b = new Button(label);
        b.getStyleClass().add("btn-primary");
        b.setMaxWidth(Double.MAX_VALUE);
        b.setOnAction(e -> {
            try {
                if (!java.awt.Desktop.isDesktopSupported()
                        || !java.awt.Desktop.getDesktop().isSupported(java.awt.Desktop.Action.MAIL)) {
                    throw new UnsupportedOperationException("no default mail client configured");
                }
                java.awt.Desktop.getDesktop().mail(URI.create(url));
            } catch (Exception ex) {
                System.err.println("[Topic] Couldn't open mail client: " + ex.getMessage());
                Alert alert = new Alert(Alert.AlertType.WARNING,
                    "Couldn't open your email client — no default mail app is configured on this computer.");
                alert.setHeaderText(null);
                alert.showAndWait();
            }
        });
        return b;
    }

    @FXML
    protected void onExportPdf() {
        new Thread(() -> {
            byte[] pdfBytes = getBytes("/api/topics/" + topicId + "/export");
            Platform.runLater(() -> {
                if (pdfBytes == null) {
                    Alert alert = new Alert(Alert.AlertType.ERROR, "Couldn't export this topic — check your connection.");
                    alert.showAndWait();
                    return;
                }
                FileChooser chooser = new FileChooser();
                chooser.setInitialFileName(topicTitleLabel.getText().replaceAll("[^a-zA-Z0-9-]+", "-") + "-discussion.pdf");
                chooser.getExtensionFilters().add(new FileChooser.ExtensionFilter("PDF files", "*.pdf"));
                File file = chooser.showSaveDialog(topicTitleLabel.getScene().getWindow());
                if (file != null) {
                    try {
                        Files.write(file.toPath(), pdfBytes);
                    } catch (IOException e) {
                        System.err.println("[Topic] Error saving PDF: " + e.getMessage());
                    }
                }
            });
        }).start();
    }

    // ---- HTTP helpers ----------------------------------------------------

    private String get(String path) {
        try {
            HttpURLConnection conn = openConnection(path, "GET");
            if (conn.getResponseCode() != 200) return null;
            return readBody(conn.getInputStream());
        } catch (Exception e) {
            System.err.println("[Topic] GET error: " + e.getMessage());
            return null;
        }
    }

    private byte[] getBytes(String path) {
        try {
            HttpURLConnection conn = openConnection(path, "GET");
            if (conn.getResponseCode() != 200) return null;
            return conn.getInputStream().readAllBytes();
        } catch (Exception e) {
            System.err.println("[Topic] GET bytes error: " + e.getMessage());
            return null;
        }
    }

    private void delete(String path) {
        try {
            HttpURLConnection conn = openConnection(path, "DELETE");
            conn.getResponseCode();
        } catch (Exception e) {
            System.err.println("[Topic] DELETE error: " + e.getMessage());
        }
    }

    /** Same null-on-success/error-message-on-failure contract as postJson(),
     *  for DELETE requests that need to know whether they actually
     *  succeeded (unlike the fire-and-forget delete() above). */
    private String deleteWithResult(String path) {
        try {
            HttpURLConnection conn = openConnection(path, "DELETE");
            int code = conn.getResponseCode();
            if (code == 200 || code == 201) return null;

            String body = readBody(conn.getErrorStream());
            try {
                return new JSONObject(body).optString("message", "The server rejected that request.");
            } catch (Exception e) {
                return "The server rejected that request.";
            }
        } catch (Exception e) {
            return "Couldn't reach the server — check your connection.";
        }
    }

    /** Returns null on success (2xx), or the server's error message. */
    private String postJson(String path, String... keyValuePairs) {
        try {
            HttpURLConnection conn = openConnection(path, "POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setDoOutput(true);

            JSONObject payload = new JSONObject();
            for (int i = 0; i < keyValuePairs.length - 1; i += 2) {
                if (keyValuePairs[i + 1] != null) payload.put(keyValuePairs[i], keyValuePairs[i + 1]);
            }
            try (OutputStream os = conn.getOutputStream()) {
                os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
            }

            int code = conn.getResponseCode();
            if (code == 200 || code == 201) return null;

            String body = readBody(conn.getErrorStream());
            try {
                return new JSONObject(body).optString("message", "The server rejected that request.");
            } catch (Exception e) {
                return "The server rejected that request.";
            }
        } catch (Exception e) {
            return "Couldn't reach the server — check your connection.";
        }
    }

    /**
     * Posts a reply as multipart/form-data instead of JSON so an optional
     * attachment file can ride along in the same request as the web's reply
     * composer does. Returns null on success (2xx), or the server's error
     * message, matching postJson()'s contract.
     */
    private String postReplyMultipart(String content, Integer parentReplyId, File attachment) {
        String boundary = "----DiscussionHubBoundary" + System.currentTimeMillis();
        try {
            HttpURLConnection conn = openConnection("/api/posts/" + mainPostId + "/replies", "POST");
            conn.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);
            conn.setDoOutput(true);

            try (OutputStream os = conn.getOutputStream()) {
                writeMultipartField(os, boundary, "ReplyContent", content);
                if (parentReplyId != null) {
                    writeMultipartField(os, boundary, "parent_reply_id", String.valueOf(parentReplyId));
                }

                if (attachment != null) {
                    String mimeType = URLConnection.guessContentTypeFromName(attachment.getName());
                    if (mimeType == null) mimeType = "application/octet-stream";

                    os.write(("--" + boundary + "\r\n").getBytes(StandardCharsets.UTF_8));
                    os.write(("Content-Disposition: form-data; name=\"attachment\"; filename=\""
                        + attachment.getName() + "\"\r\n").getBytes(StandardCharsets.UTF_8));
                    os.write(("Content-Type: " + mimeType + "\r\n\r\n").getBytes(StandardCharsets.UTF_8));
                    Files.copy(attachment.toPath(), os);
                    os.write("\r\n".getBytes(StandardCharsets.UTF_8));
                }

                os.write(("--" + boundary + "--\r\n").getBytes(StandardCharsets.UTF_8));
            }

            int code = conn.getResponseCode();
            if (code == 200 || code == 201) return null;

            String body = readBody(conn.getErrorStream());
            try {
                return new JSONObject(body).optString("message", "The server rejected that request.");
            } catch (Exception e) {
                return "The server rejected that request.";
            }
        } catch (Exception e) {
            return "Couldn't reach the server — check your connection.";
        }
    }

    private void writeMultipartField(OutputStream os, String boundary, String name, String value) throws IOException {
        os.write(("--" + boundary + "\r\n").getBytes(StandardCharsets.UTF_8));
        os.write(("Content-Disposition: form-data; name=\"" + name + "\"\r\n\r\n").getBytes(StandardCharsets.UTF_8));
        os.write((value + "\r\n").getBytes(StandardCharsets.UTF_8));
    }

    private HttpURLConnection openConnection(String path, String method) throws IOException {
        URL url = URI.create(BASE_URL + path).toURL();
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod(method);
        conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
        conn.setRequestProperty("Accept", "application/json");
        return conn;
    }

    private String readBody(InputStream stream) throws IOException {
        BufferedReader in = new BufferedReader(new InputStreamReader(stream, StandardCharsets.UTF_8));
        StringBuilder response = new StringBuilder();
        String line;
        while ((line = in.readLine()) != null) response.append(line);
        in.close();
        return response.toString();
    }

    // Mirrors topics/show.blade.php's back-link exactly: a plain, static
    // `<a href="{{ route('groups.topics', $topic->group) }}">` - always this
    // topic's OWN group's topic list, regardless of where the topic was
    // actually opened from (not real browser-history tracking). Previously
    // this always hard-navigated to the plain Forum overview instead.
    @FXML
    protected void onBack() {
        stopAutoRefresh();
        markCurrentTopicRead();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("group-topics-view.fxml"));
            Scene scene = new Scene(loader.load());
            GroupTopicsController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.setGroupContext(groupId, groupName);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — " + groupName);
        } catch (Exception e) {
            System.err.println("[Topic] Error going back: " + e.getMessage());
        }
    }
}
