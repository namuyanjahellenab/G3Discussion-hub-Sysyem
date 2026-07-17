package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

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
import java.util.HashMap;
import java.util.Iterator;
import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.Map;
import java.util.Set;

// Mirrors resources/views/topics/show.blade.php: original post, threaded
// replies (accept/flag/delete based on the same permission rules as the
// web), reply composer with the same spam/relevance moderation gate, real
// PDF export, and the full right panel (profile, Topic Info, Participants,
// Related Topics).
public class TopicController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    @FXML private ScrollPane mainScrollPane;
    @FXML private Label syncStatusLabel;
    @FXML private Label topicTitleLabel;
    @FXML private Label statusBadgeLabel;
    @FXML private Label restrictedBadgeLabel;
    @FXML private Label threadMetaLabel;
    @FXML private VBox mainPostCard;
    @FXML private VBox repliesBox;
    @FXML private Label replyContextLabel;
    @FXML private TextArea replyInput;
    @FXML private Label attachedFileLabel;
    @FXML private Button removeAttachmentButton;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
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
    private String renderedMainPostSignature;

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
        sidebarController.setOnBeforeNavigate(this::stopAutoRefresh);
        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
    }

    public void loadTopic(String topicTitle, int topicId) {
        this.topicId = topicId;
        topicTitleLabel.setText(topicTitle);
        renderedReplyRows.clear();
        renderedReplySignatures.clear();
        renderedReplyTimeLabels.clear();
        renderedMainPostSignature = null;
        refresh();
        startAutoRefresh();
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
            String body = get("/api/topics/" + topicId);
            if (body == null) return;
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> {
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
            } catch (Exception e) {
                System.err.println("[Topic] Parse error: " + e.getMessage());
            }
        }).start();
    }

    private void render(JSONObject json) {
        JSONObject topic = json.getJSONObject("topic");
        topicTitleLabel.setText(topic.getString("title"));
        statusBadgeLabel.setText(topic.getString("status"));
        boolean restricted = topic.getBoolean("is_restricted");
        restrictedBadgeLabel.setVisible(restricted);
        restrictedBadgeLabel.setManaged(restricted);

        infoGroupLabel.setText(topic.getString("group_name"));
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
        String mainAuthorName = mainPost.getString("author_name");
        Label mainAvatar = new Label(TextUtil.initials(mainAuthorName));
        mainAvatar.setMinSize(36, 36);
        mainAvatar.setMaxSize(36, 36);
        mainAvatar.setAlignment(Pos.CENTER);
        mainAvatar.setStyle("-fx-background-color: -luna-mid; -fx-text-fill: white; -fx-font-weight: bold; -fx-font-size: 13; -fx-background-radius: 18;");

        Label author = new Label(mainAuthorName);
        author.getStyleClass().add("heading-text");

        Label content = new Label(mainPost.getString("content"));
        content.setWrapText(true);
        content.setStyle("-fx-font-size: 13; -fx-text-fill: -text-body;");

        VBox authorTextColumn = new VBox(6, author, content);
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
            String signature = replySignature(reply);

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
            + "|" + reply.getBoolean("can_flag") + "|" + reply.getBoolean("can_accept") + "|" + reply.getBoolean("can_delete");
    }

    /**
     * WhatsApp/Telegram-style bubble: the current user's own replies sit on
     * the right in the theme's accent color, everyone else's sit on the left
     * in a plain card - mirrors the same is_own split as the web version's
     * topics/show.blade.php (see .reply-row.own / .reply-row.other there).
     */
    private HBox buildReplyRow(JSONObject reply) {
        boolean isOwn = reply.getBoolean("is_own");
        boolean isAccepted = reply.getBoolean("is_accepted");
        boolean isFlagged = reply.getBoolean("is_flagged");
        boolean isLecturer = reply.getBoolean("is_lecturer");
        String authorName = reply.getString("author_name");
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
        Label time = new Label(reply.getString("created_at"));
        time.getStyleClass().add("muted-text");
        time.setStyle("-fx-font-size: 10.5;");
        renderedReplyTimeLabels.put(reply.getInt("id"), time);
        top.getChildren().addAll(author, time);
        if (reply.getBoolean("can_flag")) top.getChildren().add(actionButton("🚩", () -> flagReply(reply.getInt("id"))));
        top.getChildren().add(actionButton("↩", () -> startReplyTo(reply.getInt("id"), authorName)));
        if (reply.getBoolean("can_accept")) top.getChildren().add(actionButton("✔", () -> acceptAnswer(reply.getInt("id"))));
        if (reply.getBoolean("can_delete")) top.getChildren().add(actionButton("🗑", () -> deleteReply(reply.getInt("id"))));

        VBox bubble = new VBox(6);
        bubble.setMaxWidth(420);
        String bg = isAccepted ? "-accent-success-bg" : isFlagged ? "-accent-danger-bg" : (isOwn ? "-luna-mid" : "-surface-card");
        String border = isAccepted ? "-fx-border-color: -accent-success; -fx-border-width: 2;"
            : isFlagged ? "-fx-border-color: -accent-danger; -fx-border-width: 1;"
            : (!isOwn ? "-fx-border-color: -surface-border; -fx-border-width: 1;" : "");
        String radius = isOwn ? "-fx-background-radius: 14 4 14 14; -fx-border-radius: 14 4 14 14;"
            : "-fx-background-radius: 4 14 14 14; -fx-border-radius: 4 14 14 14;";
        bubble.setStyle("-fx-background-color: " + bg + "; " + radius + " " + border + " -fx-padding: 10 14;");

        if (!reply.isNull("parent_reply_author") && reply.getString("parent_reply_author") != null) {
            Label quote = new Label("↩ Replying to " + reply.getString("parent_reply_author") + ": " + reply.optString("parent_reply_snippet", ""));
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

        Label content = new Label(reply.getString("content"));
        content.setWrapText(true);
        content.setMaxWidth(380);
        content.setStyle("-fx-font-size: 13; -fx-text-fill: " + textColor + ";");
        bubble.getChildren().add(content);

        if (!reply.isNull("attachment_url")) {
            bubble.getChildren().add(buildAttachmentNode(
                reply.getString("attachment_url"),
                reply.optString("attachment_type", "file"),
                reply.optString("attachment_name", "attachment")
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

    private javafx.scene.Node buildAttachmentNode(String url, String type, String name) {
        String fullUrl = url.startsWith("http") ? url : BASE_URL + url;

        if ("image".equals(type)) {
            ImageView imageView = new ImageView(new Image(fullUrl, 260, 0, true, true, true));
            imageView.setPreserveRatio(true);
            imageView.setFitWidth(260);
            imageView.setStyle("-fx-cursor: hand;");
            imageView.setOnMouseClicked(e -> openInBrowser(fullUrl));
            VBox.setMargin(imageView, new Insets(4, 0, 0, 0));
            return imageView;
        }

        Label fileLink = new Label("📎 " + name);
        fileLink.setStyle("-fx-text-fill: #26658C; -fx-font-weight: bold; -fx-font-size: 12; -fx-cursor: hand; -fx-underline: true;");
        fileLink.setOnMouseClicked(e -> openInBrowser(fullUrl));
        return fileLink;
    }

    private void openInBrowser(String url) {
        try {
            java.awt.Desktop.getDesktop().browse(URI.create(url));
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
        replyContextLabel.setText("Replying to " + authorName + "  (✕ to cancel)");
        replyContextLabel.setVisible(true);
        replyContextLabel.setManaged(true);
        replyContextLabel.setOnMouseClicked(e -> {
            replyToId = null;
            replyContextLabel.setVisible(false);
            replyContextLabel.setManaged(false);
        });
        replyInput.requestFocus();
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

        new Thread(() -> {
            String error = postReplyMultipart(content, parentReplyId, attachment);
            Platform.runLater(() -> {
                replyInput.setDisable(false);
                if (error != null) {
                    Alert alert = new Alert(Alert.AlertType.WARNING, error);
                    alert.setHeaderText(null);
                    alert.showAndWait();
                } else {
                    replyInput.clear();
                    replyToId = null;
                    replyContextLabel.setVisible(false);
                    replyContextLabel.setManaged(false);
                    onRemoveAttachment();
                    refresh();
                }
            });
        }).start();
    }

    private void flagReply(int replyId) {
        new Thread(() -> {
            postJson("/api/replies/" + replyId + "/flag", "Reason", null);
            Platform.runLater(this::refresh);
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
        Button email = shareLink("✉ Email", links.getString("email"));

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

    @FXML
    protected void onBack() {
        stopAutoRefresh();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("forum-view.fxml"));
            Scene scene = new Scene(loader.load());
            ForumController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Forum");
        } catch (Exception e) {
            System.err.println("[Topic] Error going back: " + e.getMessage());
        }
    }
}
