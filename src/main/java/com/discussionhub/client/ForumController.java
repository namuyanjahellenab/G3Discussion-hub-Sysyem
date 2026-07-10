package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.stage.Modality;
import javafx.stage.Stage;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

/**
 * Group forum screen: lists topics for one specific group exactly like the
 * web app's forum/group.blade.php (backed by DiscussionHubPageController
 * ::groupTopics()), via GET /api/groups/{group}/topics. Replaces the old
 * SQLite-only "fake" listing with the real, server-driven behaviour
 * (search, status filter, pinned/answered/open/discussion badges,
 * server-side pagination) so topic creation and browsing match the web
 * app one-to-one.
 */
public class ForumController {

    @FXML private Label syncStatusLabel;
    @FXML private TextField searchField;
    @FXML private ComboBox<String> statusFilter;
    @FXML private ListView<TopicRow> topicListView;
    @FXML private HBox offlineBanner;
    @FXML private Label pageLabel;
    @FXML private Button prevButton;
    @FXML private Button nextButton;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    private int currentPage = 1;
    private int lastPage = 1;

    // Set via setGroupContext() when opened from a group card on the Dashboard.
    private int currentGroupId = 0;
    private String currentGroupName = "";

    private static final String BASE_URL = "http://localhost:8000/api";

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        setupTopicCells();
        setupStatusFilter();
        updateSyncStatusLabel();
    }

    /**
     * Call this right after setServices() when opening the Forum for one
     * specific group (e.g. from clicking a group card on the Dashboard).
     */
    public void setGroupContext(int groupId, String groupName) {
        this.currentGroupId = groupId;
        this.currentGroupName = groupName;
        currentPage = 1;
        loadTopics();
        markGroupAsViewed(groupId);
    }

    private void markGroupAsViewed(int groupId) {
        new Thread(() -> {
            try {
                URL url = URI.create(BASE_URL + "/groups/" + groupId + "/mark-viewed").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);
                conn.getOutputStream().write(new byte[0]);
                conn.getResponseCode(); // actually sends the request
            } catch (Exception e) {
                System.err.println("[Forum] Error marking group as viewed: " + e.getMessage());
            }
        }).start();
    }

    /**
     * Custom cell mirroring forum/group.blade.php's topic row: pinned/status
     * badge, title, author, reply count, and relative time.
     */
    private void setupTopicCells() {
        topicListView.setCellFactory(listView -> new ListCell<>() {
            @Override
            protected void updateItem(TopicRow item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setGraphic(null); return; }

                HBox row = new HBox(12);
                row.setStyle("-fx-padding: 10; -fx-alignment: CENTER_LEFT;");
                row.setPrefWidth(Double.MAX_VALUE);

                Label badge = new Label(item.isPinned ? "PINNED" : statusLabelFor(item.status));
                badge.setStyle(badgeStyleFor(item.isPinned ? "pinned" : item.status));

                Label titleLabel = new Label(item.title);
                titleLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 13;");
                HBox.setHgrow(titleLabel, Priority.ALWAYS);

                Label metaLabel = new Label("by " + item.author + "  •  "
                    + item.replyCount + (item.replyCount == 1 ? " reply" : " replies")
                    + "  •  " + item.createdAtHuman);
                metaLabel.setStyle("-fx-text-fill: #888; -fx-font-size: 11;");

                row.getChildren().addAll(badge, titleLabel, metaLabel);
                setGraphic(row);
            }
        });
    }

    private String statusLabelFor(String status) {
        return switch (status) {
            case "answered" -> "ANSWERED";
            case "discussion" -> "DISCUSSION";
            default -> "OPEN";
        };
    }

    // Mirrors the .badge-* CSS classes in forum/group.blade.php
    private String badgeStyleFor(String kind) {
        String bg, fg;
        switch (kind) {
            case "pinned" -> { bg = "#eef4ff"; fg = "#0d52cc"; }
            case "answered" -> { bg = "#ecfdf3"; fg = "#12b76a"; }
            case "discussion" -> { bg = "#fef0ff"; fg = "#9e77ed"; }
            default -> { bg = "#f2f4f7"; fg = "#667085"; }
        }
        return String.format(
            "-fx-background-color: %s; -fx-text-fill: %s; -fx-font-weight: bold;" +
                "-fx-font-size: 10; -fx-padding: 3 8; -fx-background-radius: 6;", bg, fg);
    }

    private void setupStatusFilter() {
        statusFilter.getItems().setAll("All", "Open", "Answered", "Discussion");
        statusFilter.getSelectionModel().selectFirst();
    }

    private void updateSyncStatusLabel() {
        boolean isOnline = NetworkUtil.isNetworkAvailable();
        if (isOnline) {
            syncStatusLabel.setText("● ONLINE");
            syncStatusLabel.setStyle("-fx-text-fill: #90ee90; -fx-font-size: 12; -fx-font-weight: bold;");
            offlineBanner.setVisible(false);
            offlineBanner.setManaged(false);
        } else {
            syncStatusLabel.setText("● OFFLINE");
            syncStatusLabel.setStyle("-fx-text-fill: #ffcc00; -fx-font-size: 12; -fx-font-weight: bold;");
            offlineBanner.setVisible(true);
            offlineBanner.setManaged(true);
        }
    }

    /**
     * Fetches this page of topics from GET /api/groups/{group}/topics —
     * the exact same query (search + filter + pinned-first ordering +
     * 5-per-page pagination) as DiscussionHubPageController::groupTopics().
     */
    private void loadTopics() {
        if (currentGroupId == 0) return;

        String search = searchField.getText() == null ? "" : searchField.getText().trim();
        String filter = statusFilter.getSelectionModel().getSelectedItem();
        String filterParam = (filter == null || filter.equals("All")) ? "all" : filter.toLowerCase();
        int pageToLoad = currentPage;

        new Thread(() -> {
            try {
                String query = "?filter=" + URLEncoder.encode(filterParam, StandardCharsets.UTF_8)
                    + "&page=" + pageToLoad
                    + (search.isEmpty() ? "" : "&search=" + URLEncoder.encode(search, StandardCharsets.UTF_8));

                URL url = URI.create(BASE_URL + "/groups/" + currentGroupId + "/topics" + query).toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("GET");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");

                int responseCode = conn.getResponseCode();
                if (responseCode == 200) {
                    String body = readBody(conn);
                    JSONObject json = new JSONObject(body);
                    JSONArray topicsJson = json.getJSONArray("topics");
                    List<TopicRow> rows = new ArrayList<>();
                    for (int i = 0; i < topicsJson.length(); i++) {
                        JSONObject t = topicsJson.getJSONObject(i);
                        rows.add(new TopicRow(
                            t.getInt("id"),
                            t.getString("title"),
                            t.optString("status", "open"),
                            t.optBoolean("is_pinned", false),
                            t.optInt("reply_count", 0),
                            t.optString("author", "a member"),
                            t.optString("created_at_human", "")
                        ));
                    }
                    int newLastPage = json.optInt("last_page", 1);

                    Platform.runLater(() -> {
                        lastPage = newLastPage;
                        topicListView.getItems().setAll(rows);
                        pageLabel.setText("Page " + pageToLoad + " of " + lastPage);
                        prevButton.setDisable(pageToLoad <= 1);
                        nextButton.setDisable(pageToLoad >= lastPage);
                    });
                } else {
                    Platform.runLater(() -> showError("Could not load topics (server returned " + responseCode + ")."));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showError("Error loading topics: " + e.getMessage()));
            }
        }).start();
    }

    @FXML protected void onSearch() { currentPage = 1; loadTopics(); }
    @FXML protected void onFilterChange() { currentPage = 1; loadTopics(); }
    @FXML protected void onPrevPage() { if (currentPage > 1) { currentPage--; loadTopics(); } }
    @FXML protected void onNextPage() { if (currentPage < lastPage) { currentPage++; loadTopics(); } }

    @FXML
    protected void onTopicSelected() {
        TopicRow selected = topicListView.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("topic-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            TopicController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.loadTopic(selected.id);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — " + selected.title);
        } catch (Exception e) {
            System.err.println("[Forum] Error opening topic: " + e.getMessage());
        }
    }

    /**
     * New-topic dialog matching topics/create.blade.php's fields (Title +
     * Content) and posts to POST /api/topics — the same validation and
     * create logic as DiscussionHubPageController::storeTopic().
     */
    @FXML
    protected void onNewTopic() {
        if (currentGroupId == 0) {
            showError("Open this screen from a specific group to create a topic.");
            return;
        }

        Dialog<String[]> dialog = new Dialog<>();
        dialog.setTitle("New Topic");
        dialog.setHeaderText("Create a new discussion topic in " + currentGroupName);
        dialog.initModality(Modality.APPLICATION_MODAL);

        ButtonType createButton = new ButtonType("Create", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(createButton, ButtonType.CANCEL);

        TextField titleField = new TextField();
        titleField.setPromptText("Topic title");
        TextArea contentArea = new TextArea();
        contentArea.setPromptText("What would you like to discuss?");
        contentArea.setPrefRowCount(5);
        contentArea.setWrapText(true);

        javafx.scene.layout.VBox content = new javafx.scene.layout.VBox(10,
            new Label("Title:"), titleField,
            new Label("Content:"), contentArea);
        content.setPadding(new javafx.geometry.Insets(16));
        content.setPrefWidth(420);
        dialog.getDialogPane().setContent(content);

        dialog.setResultConverter(btn ->
            btn == createButton ? new String[]{titleField.getText(), contentArea.getText()} : null);

        dialog.showAndWait().ifPresent(result -> {
            String title = result[0] == null ? "" : result[0].trim();
            String contentText = result[1] == null ? "" : result[1].trim();
            if (title.isEmpty() || contentText.isEmpty()) {
                showError("Both a title and some content are required.");
                return;
            }
            createTopic(title, contentText);
        });
    }

    private void createTopic(String title, String contentText) {
        new Thread(() -> {
            try {
                URL url = URI.create(BASE_URL + "/topics").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                JSONObject payload = new JSONObject();
                payload.put("Title", title);
                payload.put("GroupID", currentGroupId);
                payload.put("Content", contentText);

                try (OutputStream os = conn.getOutputStream()) {
                    os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
                }

                int code = conn.getResponseCode();
                if (code == 201) {
                    Platform.runLater(() -> { currentPage = 1; loadTopics(); });
                } else {
                    String err = readErrorBody(conn);
                    Platform.runLater(() -> showError("Could not create topic: " + err));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showError("Error creating topic: " + e.getMessage()));
            }
        }).start();
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Desktop Client");
        } catch (Exception e) {
            System.err.println("[Forum] Error going back: " + e.getMessage());
        }
    }

    private void showError(String message) {
        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle("DiscussionHub");
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private static String readBody(HttpURLConnection conn) throws Exception {
        StringBuilder sb = new StringBuilder();
        try (BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
            String line;
            while ((line = in.readLine()) != null) sb.append(line);
        }
        return sb.toString();
    }

    private static String readErrorBody(HttpURLConnection conn) {
        try (BufferedReader in = new BufferedReader(new InputStreamReader(conn.getErrorStream()))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = in.readLine()) != null) sb.append(line);
            JSONObject err = new JSONObject(sb.toString());
            return err.optString("message", "unknown error");
        } catch (Exception e) {
            return "unknown error";
        }
    }

    private static class TopicRow {
        final int id;
        final String title;
        final String status;
        final boolean isPinned;
        final int replyCount;
        final String author;
        final String createdAtHuman;

        TopicRow(int id, String title, String status, boolean isPinned,
                 int replyCount, String author, String createdAtHuman) {
            this.id = id;
            this.title = title;
            this.status = status;
            this.isPinned = isPinned;
            this.replyCount = replyCount;
            this.author = author;
            this.createdAtHuman = createdAtHuman;
        }
    }
}
