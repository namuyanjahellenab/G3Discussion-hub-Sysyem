package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import javafx.animation.PauseTransition;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.util.Duration;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

// Mirrors forum/group.blade.php exactly: search, filter chips with live
// counts (all/open/flagged/restricted), badges per topic (pinned, status,
// restricted, flagged), pagination, and New Topic creation with the same
// moderation gate as the web.
public class GroupTopicsController {

    private static final String BASE_URL = "http://127.0.0.1:8000";
    private static final Map<String, String> FILTERS = new LinkedHashMap<>();
    static {
        FILTERS.put("all", "All");
        FILTERS.put("open", "Open");
        FILTERS.put("flagged", "Flagged");
        FILTERS.put("restricted", "Restricted audience");
    }

    @FXML private Label syncStatusLabel;
    @FXML private Label breadcrumbLabel;
    @FXML private Label groupTitleLabel;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private Label groupTopicCountLabel;
    @FXML private TextField searchField;
    @FXML private HBox filterRow;
    @FXML private VBox topicsBox;
    @FXML private Label paginationLabel;
    @FXML private Button prevButton;
    @FXML private Button nextButton;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private int groupId;
    private String groupName;
    private String currentFilter = "all";
    private int currentPage = 1;
    private int lastPage = 1;
    private JSONArray groupMembers = new JSONArray();

    // SDD 6.2 requires the topic list to filter in real time as the user
    // types; debounced so we don't fire a request per keystroke.
    private final PauseTransition searchDebounce = new PauseTransition(Duration.millis(350));

    @FXML
    private void initialize() {
        searchDebounce.setOnFinished(e -> {
            currentPage = 1;
            loadTopics();
        });
        searchField.textProperty().addListener((obs, oldVal, newVal) -> searchDebounce.playFromStart());
    }

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("forum");
        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
        userRoleLabel.setText((SessionManager.role == null || SessionManager.role.isBlank() ? "Student" : SessionManager.role) + " Account");
    }

    public void setGroupContext(int groupId, String groupName) {
        this.groupId = groupId;
        this.groupName = groupName;
        breadcrumbLabel.setText("Home › " + groupName + " › Forum");
        groupTitleLabel.setText(groupName);
        loadTopics();
    }

    private void loadTopics() {
        String search = searchField.getText().trim();
        new Thread(() -> {
            boolean online = com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable();
            // Opportunistic: flushes any queued topics/replies and pulls
            // fresh Topic/Post rows into the local cache whenever we
            // actually have a connection, so the cache used below is never
            // more stale than "last time this screen was open while online".
            if (online) syncService.synchronizeLocalChanges();

            String path = "/api/groups/" + groupId + "/topics?filter=" + currentFilter + "&page=" + currentPage
                + (search.isEmpty() ? "" : "&search=" + java.net.URLEncoder.encode(search, StandardCharsets.UTF_8));
            String body = online ? get(path) : null;

            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    Platform.runLater(() -> { markOnline(); render(json); });
                    return;
                } catch (Exception e) {
                    System.err.println("[GroupTopics] Parse error: " + e.getMessage());
                }
            }

            // Offline, or the live call failed anyway - fall back to
            // whatever was last cached for this group. Search/filter/
            // pagination aren't reproducible against the local cache, so
            // this shows everything cached instead, unfiltered.
            List<com.discussionhub.client.database.CachedTopicItem> cached = dbManager.getCachedTopics(groupId);
            Platform.runLater(() -> { markOffline(); renderCached(cached); });
        }).start();
    }

    private void markOnline() {
        syncStatusLabel.setText("● ONLINE");
        syncStatusLabel.setStyle("-fx-text-fill: #3F9C6B; -fx-font-size: 12; -fx-font-weight: bold;");
    }

    private void markOffline() {
        syncStatusLabel.setText("● OFFLINE — showing saved topics");
        syncStatusLabel.setStyle("-fx-text-fill: #D9483D; -fx-font-size: 12; -fx-font-weight: bold;");
    }

    private void renderCached(List<com.discussionhub.client.database.CachedTopicItem> topics) {
        filterRow.getChildren().clear();
        topicsBox.getChildren().clear();
        if (topics.isEmpty()) {
            Label empty = new Label("No saved topics for this group yet — connect once to cache them for offline use.");
            empty.getStyleClass().add("muted-text");
            empty.setWrapText(true);
            empty.setStyle("-fx-padding: 32 20;");
            topicsBox.getChildren().add(empty);
        } else {
            for (com.discussionhub.client.database.CachedTopicItem t : topics) {
                topicsBox.getChildren().add(createCachedTopicRow(t));
            }
        }
        paginationLabel.setText(topics.size() + " saved " + (topics.size() == 1 ? "topic" : "topics"));
        prevButton.setDisable(true);
        nextButton.setDisable(true);
        groupTopicCountLabel.setText(String.valueOf(topics.size()));
    }

    private VBox createCachedTopicRow(com.discussionhub.client.database.CachedTopicItem topic) {
        VBox row = new VBox(6);
        boolean clickable = topic.getTopicId() > 0; // a still-pending offline topic has no server id to open yet
        row.setStyle("-fx-padding: 16 20; -fx-border-color: transparent transparent #E1E9ED transparent; -fx-border-width: 0 0 1 0;"
            + (clickable ? " -fx-cursor: hand;" : ""));
        if (clickable) {
            row.setOnMouseClicked(e -> openTopic(topic.getTopicId(), topic.getTitle()));
        }

        HBox badges = new HBox(6);
        if (topic.isPending()) badges.getChildren().add(badge("⏳ Pending — will post when online", "#FBF0E1", "#D98E3D"));
        Label title = new Label(topic.getTitle());
        title.getStyleClass().add("heading-text");
        title.setStyle("-fx-font-size: 13.5;");
        HBox titleRow = new HBox(8, title);
        titleRow.setAlignment(Pos.CENTER_LEFT);
        addUnreadBadge(titleRow, topic.getTopicId());
        Label meta = new Label(TextUtil.timeAgo(topic.getCreatedAt()));
        meta.getStyleClass().add("muted-text");

        row.getChildren().addAll(badges, titleRow, meta);
        return row;
    }

    /** Local, entirely offline-capable unread count (see
     *  DatabaseManager.countUnreadReplies()) - a small red dot+count next to
     *  the title, same idea as WhatsApp's per-chat unread badge, gone once
     *  the topic has been opened and TopicController marks it read. */
    private void addUnreadBadge(HBox titleRow, int topicId) {
        if (topicId <= 0) return; // still-pending offline topic, nothing to count against
        int unread = dbManager.countUnreadReplies(topicId);
        if (unread <= 0) return;
        Label dot = new Label(unread > 99 ? "99+" : String.valueOf(unread));
        dot.setStyle("-fx-background-color: #D9483D; -fx-text-fill: white; -fx-font-size: 10; -fx-font-weight: bold; " +
            "-fx-padding: 1 7; -fx-background-radius: 999;");
        titleRow.getChildren().add(dot);
    }

    private void render(JSONObject json) {
        JSONArray members = json.optJSONArray("group_members");
        groupMembers = members != null ? members : new JSONArray();

        JSONObject counts = json.getJSONObject("filter_counts");
        filterRow.getChildren().clear();
        for (Map.Entry<String, String> entry : FILTERS.entrySet()) {
            String key = entry.getKey();
            Button chip = new Button(entry.getValue() + " (" + counts.optInt(key, 0) + ")");
            chip.getStyleClass().add(key.equals(currentFilter) ? "sidebar-link-active" : "sidebar-link");
            chip.setStyle(chip.getStyle() + "-fx-background-radius: 999; -fx-font-size: 12.5;");
            chip.setOnAction(e -> {
                currentFilter = key;
                currentPage = 1;
                loadTopics();
            });
            filterRow.getChildren().add(chip);
        }

        var topics = json.getJSONArray("topics");
        topicsBox.getChildren().clear();
        if (topics.isEmpty()) {
            Label empty = new Label("No topics found.");
            empty.getStyleClass().add("muted-text");
            empty.setStyle("-fx-padding: 32 20;");
            topicsBox.getChildren().add(empty);
        } else {
            for (int i = 0; i < topics.length(); i++) {
                topicsBox.getChildren().add(createTopicRow(topics.getJSONObject(i)));
            }
        }

        int total = json.getInt("total");
        currentPage = json.getInt("page");
        lastPage = json.getInt("last_page");
        int from = total == 0 ? 0 : (currentPage - 1) * 5 + 1;
        int to = Math.min(currentPage * 5, total);
        paginationLabel.setText("Showing " + from + "-" + to + " of " + total + " topics");
        prevButton.setDisable(currentPage <= 1);
        nextButton.setDisable(currentPage >= lastPage);

        groupTopicCountLabel.setText(String.valueOf(total));
    }

    private VBox createTopicRow(JSONObject topic) {
        VBox row = new VBox(6);
        row.setStyle("-fx-padding: 16 20; -fx-border-color: transparent transparent #E1E9ED transparent; -fx-border-width: 0 0 1 0; -fx-cursor: hand;");
        row.setOnMouseClicked(e -> openTopic(topic.getInt("id"), topic.getString("title")));

        HBox badges = new HBox(6);
        if (topic.getBoolean("is_pinned")) badges.getChildren().add(badge("Pinned", "#26658C", "white"));
        String status = topic.optString("status", "open");
        String statusLabel = status.equals("answered") ? "Answered" : status.equals("discussion") ? "Discussion" : "Open";
        badges.getChildren().add(badge(statusLabel, "#E1E9ED", "#33455A"));
        if (topic.getBoolean("is_restricted")) badges.getChildren().add(badge("👁 Restricted audience", "#F4F8FA", "#6B8094"));
        int flaggedCount = topic.optInt("flagged_replies_count", 0);
        if (flaggedCount > 0) badges.getChildren().add(badge("🚩 " + flaggedCount + " flagged " + (flaggedCount == 1 ? "reply" : "replies"), "#FBE7E5", "#D9483D"));

        Label title = new Label(topic.getString("title"));
        title.getStyleClass().add("heading-text");
        title.setStyle("-fx-font-size: 13.5;");
        HBox titleRow = new HBox(8, title);
        titleRow.setAlignment(Pos.CENTER_LEFT);
        addUnreadBadge(titleRow, topic.getInt("id"));

        int postsCount = topic.optInt("posts_count", 0);
        Label meta = new Label("by " + topic.optString("creator_name", "a member")
            + " • " + postsCount + " " + (postsCount == 1 ? "reply" : "replies")
            + " • last activity " + topic.optString("last_activity", ""));
        meta.getStyleClass().add("muted-text");

        row.getChildren().addAll(badges, titleRow, meta);
        return row;
    }

    private Label badge(String text, String bg, String fg) {
        Label l = new Label(text);
        l.setStyle("-fx-background-color: " + bg + "; -fx-text-fill: " + fg + "; " +
            "-fx-padding: 2 10; -fx-background-radius: 999; -fx-font-size: 10.5; -fx-font-weight: bold;");
        return l;
    }

    private void openTopic(int topicId, String title) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("topic-view.fxml"));
            Scene scene = new Scene(loader.load());
            TopicController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.loadTopic(title, topicId);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — " + title);
        } catch (Exception e) {
            System.err.println("[GroupTopics] Error opening topic: " + e.getMessage());
        }
    }

    @FXML protected void onSearch() { searchDebounce.stop(); currentPage = 1; loadTopics(); }
    @FXML protected void onPrevPage() { if (currentPage > 1) { currentPage--; loadTopics(); } }
    @FXML protected void onNextPage() { if (currentPage < lastPage) { currentPage++; loadTopics(); } }

    // Result: [0]=title, [1]=content, [2]="everyone"/"custom", [3..]=excluded UserIDs (as strings)
    @FXML
    protected void onNewTopic() {
        Dialog<List<String>> dialog = new Dialog<>();
        dialog.setTitle("New Topic");
        dialog.setHeaderText("Create a new discussion topic in " + groupName);
        dialog.initModality(Modality.APPLICATION_MODAL);
        dialog.setResizable(true);

        ButtonType createButton = new ButtonType("Create", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(createButton, ButtonType.CANCEL);

        TextField titleField = new TextField();
        titleField.setPromptText("Topic title");
        TextArea contentArea = new TextArea();
        contentArea.setPromptText("What would you like to discuss?");
        contentArea.setPrefRowCount(4);

        // "Post to: Everyone / Custom audience" — mirrors topics/create.blade.php
        ToggleGroup audienceGroup = new ToggleGroup();
        ToggleButton everyoneToggle = new ToggleButton("Everyone (" + (groupMembers.length() + 1) + ")");
        ToggleButton customToggle = new ToggleButton("Custom audience ▾");
        everyoneToggle.setToggleGroup(audienceGroup);
        customToggle.setToggleGroup(audienceGroup);
        everyoneToggle.setSelected(true);
        HBox audienceToggleRow = new HBox(8, everyoneToggle, customToggle);

        VBox excludeBox = new VBox(4);
        List<CheckBox> excludeCheckboxes = new ArrayList<>();
        for (int i = 0; i < groupMembers.length(); i++) {
            JSONObject member = groupMembers.getJSONObject(i);
            CheckBox cb = new CheckBox(member.getString("name"));
            cb.setUserData(member.getInt("id"));
            excludeCheckboxes.add(cb);
            excludeBox.getChildren().add(cb);
        }
        if (excludeCheckboxes.isEmpty()) {
            Label none = new Label("No other members in this group yet.");
            none.getStyleClass().add("muted-text");
            excludeBox.getChildren().add(none);
        }
        // The member list can be long, so it scrolls within a capped height
        // instead of pushing the Create/Cancel buttons off the bottom of
        // the dialog (or getting clipped by it).
        ScrollPane excludeScroll = new ScrollPane(excludeBox);
        excludeScroll.setFitToWidth(true);
        excludeScroll.setPrefHeight(160);
        excludeScroll.setMaxHeight(160);
        VBox excludePanel = new VBox(6, new Label("Exclude from this topic:"), excludeScroll);
        excludePanel.setVisible(false);
        excludePanel.setManaged(false);
        customToggle.setOnAction(e -> {
            excludePanel.setVisible(true);
            excludePanel.setManaged(true);
        });
        everyoneToggle.setOnAction(e -> {
            excludePanel.setVisible(false);
            excludePanel.setManaged(false);
        });

        VBox content = new VBox(10,
            new Label("Title:"), titleField,
            new Label("Content:"), contentArea,
            new Label("Post to:"), audienceToggleRow, excludePanel);
        content.setPadding(new Insets(16));
        content.setPrefWidth(380);
        dialog.getDialogPane().setContent(content);
        dialog.getDialogPane().setPrefHeight(560);

        dialog.setResultConverter(btn -> {
            if (btn != createButton) return null;
            List<String> result = new ArrayList<>();
            result.add(titleField.getText());
            result.add(contentArea.getText());
            result.add(customToggle.isSelected() ? "custom" : "everyone");
            if (customToggle.isSelected()) {
                for (CheckBox cb : excludeCheckboxes) {
                    if (cb.isSelected()) result.add(String.valueOf(cb.getUserData()));
                }
            }
            return result;
        });

        dialog.showAndWait().ifPresent(result -> {
            String title = result.get(0).trim();
            String contentText = result.get(1).trim();
            String audience = result.get(2);
            List<String> excludeIds = result.subList(3, result.size());
            if (title.isEmpty() || contentText.isEmpty()) return;

            new Thread(() -> {
                // Composing a topic offline used to just fail outright (the
                // POST has nowhere to go) - queue it the same way an offline
                // chat message already does (queuePendingMessage), instead
                // of discarding what the student just wrote.
                if (!com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable()) {
                    JSONArray excludeArray = new JSONArray();
                    if ("custom".equals(audience)) {
                        for (String id : excludeIds) excludeArray.put(Integer.parseInt(id));
                    }
                    dbManager.queuePendingTopic(groupId, groupName, SessionManager.userId, title, contentText,
                        audience, excludeArray.toString());
                    Platform.runLater(() -> {
                        showStatus("You're offline — this topic will be posted once you're back online.");
                        loadTopics();
                    });
                    return;
                }

                String error = postTopic(title, contentText, audience, excludeIds);
                Platform.runLater(() -> {
                    if (error != null) {
                        Alert alert = new Alert(Alert.AlertType.WARNING, error);
                        alert.setHeaderText(null);
                        alert.showAndWait();
                    } else {
                        loadTopics();
                    }
                });
            }).start();
        });
    }

    private void showStatus(String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION, message);
        alert.setHeaderText(null);
        alert.showAndWait();
    }

    /** Returns null on success, or the server's error message. */
    private String postTopic(String title, String contentText, String audience, List<String> excludeIds) {
        try {
            URL url = URI.create(BASE_URL + "/api/groups/" + groupId + "/topics").toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setDoOutput(true);

            JSONObject payload = new JSONObject();
            payload.put("title", title);
            payload.put("content", contentText);
            payload.put("audience", audience);
            if ("custom".equals(audience) && !excludeIds.isEmpty()) {
                JSONArray excludeArray = new JSONArray();
                for (String id : excludeIds) excludeArray.put(Integer.parseInt(id));
                payload.put("exclude", excludeArray);
            }
            try (OutputStream os = conn.getOutputStream()) {
                os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
            }

            int code = conn.getResponseCode();
            if (code == 200 || code == 201) return null;

            String body = readBody(conn.getErrorStream());
            try {
                return new JSONObject(body).optString("message", "Couldn't create the topic.");
            } catch (Exception e) {
                return "Couldn't create the topic.";
            }
        } catch (Exception e) {
            return "Couldn't reach the server — check your connection.";
        }
    }

    private String readBody(java.io.InputStream stream) throws IOException {
        BufferedReader in = new BufferedReader(new InputStreamReader(stream, StandardCharsets.UTF_8));
        StringBuilder response = new StringBuilder();
        String line;
        while ((line = in.readLine()) != null) response.append(line);
        in.close();
        return response.toString();
    }

    private String get(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            if (conn.getResponseCode() != 200) return null;
            return readBody(conn.getInputStream());
        } catch (Exception e) {
            System.err.println("[GroupTopics] Request error: " + e.getMessage());
            return null;
        }
    }

    @FXML
    protected void onBackToForum() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("forum-view.fxml"));
            Scene scene = new Scene(loader.load());
            ForumController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Forum");
        } catch (Exception e) {
            System.err.println("[GroupTopics] Error going back: " + e.getMessage());
        }
    }
}
