package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import com.discussionhub.client.utils.WindowUtil;
import com.discussionhub.client.utils.AppConfig;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

// Mirrors resources/views/recommend/index.blade.php: interest tags, ranked
// topic list, Refresh/Dismiss All actions, and the right profile/tip panel -
// previously this screen was just the generic simple-list-view.fxml card
// list (SimpleListController.loadRecommend(), now removed), which had none
// of those.
public class RecommendController {

    private static final String BASE_URL = AppConfig.BASE_URL;

    @FXML private Label syncStatusLabel;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private FlowPane interestTagsBox;
    @FXML private VBox topicsBox;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("recommend");

        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
        userRoleLabel.setText((SessionManager.role == null || SessionManager.role.isBlank() ? "Student" : SessionManager.role) + " Account");

        loadRecommendations();
    }

    @FXML
    protected void onRefresh() {
        loadRecommendations();
    }

    // /api/recommend always computes live (no persisted Recommendation rows
    // to delete the way the web's dismiss does) - so on desktop, "dismiss"
    // is purely a client-side clear. Refresh (a plain re-fetch) already
    // regenerates it from scratch either way.
    @FXML
    protected void onDismissAll() {
        topicsBox.getChildren().clear();
        showEmptyTopics();
    }

    private void loadRecommendations() {
        interestTagsBox.getChildren().clear();
        topicsBox.getChildren().clear();
        Label loading = new Label("Loading…");
        loading.getStyleClass().add("muted-text");
        loading.setStyle("-fx-padding: 16 20;");
        topicsBox.getChildren().add(loading);

        new Thread(() -> {
            String body = get("/api/recommend");
            if (body == null) {
                Platform.runLater(() -> showEmptyTopics("Couldn't reach the server — check your connection."));
                return;
            }
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> render(json));
            } catch (Exception e) {
                System.err.println("[Recommend] Parse error: " + e.getMessage());
                Platform.runLater(() -> showEmptyTopics("Couldn't load recommendations — please try again."));
            }
        }).start();
    }

    private void render(JSONObject json) {
        interestTagsBox.getChildren().clear();
        JSONArray interests = json.optJSONArray("interests");
        if (interests != null) {
            for (int i = 0; i < interests.length(); i++) {
                Label tag = new Label(interests.getString(i));
                tag.setStyle("-fx-background-color: -luna-lightest; -fx-text-fill: -luna-dark; " +
                    "-fx-font-size: 11; -fx-font-weight: bold; -fx-padding: 6 14; -fx-background-radius: 999;");
                interestTagsBox.getChildren().add(tag);
            }
        }

        topicsBox.getChildren().clear();
        JSONArray topics = json.optJSONArray("topics");
        if (topics == null || topics.isEmpty()) {
            showEmptyTopics();
            return;
        }
        for (int i = 0; i < topics.length(); i++) {
            topicsBox.getChildren().add(buildTopicRow(topics.getJSONObject(i)));
        }
    }

    private HBox buildTopicRow(JSONObject t) {
        HBox row = new HBox(16);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-padding: 16 20; -fx-border-color: transparent transparent -surface-border transparent; " +
            "-fx-border-width: 0 0 1 0; -fx-cursor: hand;");
        row.setOnMouseClicked(e -> openTopic(t.getInt("id"), t.getString("title")));

        VBox main = new VBox(6);
        HBox.setHgrow(main, Priority.ALWAYS);
        Label title = new Label(t.getString("title"));
        title.getStyleClass().add("heading-text");
        title.setStyle("-fx-font-size: 13.5;");
        title.setWrapText(true);

        String metaText = t.optString("category", "General")
            + "  ·  " + t.optString("creator_name", "a member")
            + "  ·  " + t.optString("group_name", "General")
            + "  ·  " + t.optString("created_at", "");
        Label meta = new Label(metaText);
        meta.getStyleClass().add("muted-text");
        meta.setStyle("-fx-font-size: 12;");
        main.getChildren().addAll(title, meta);

        row.getChildren().addAll(main, matchBadge(t.optDouble("relevance_score", 0)));
        return row;
    }

    /** Matches recommend/index.blade.php's .match-badge tiers (high/medium/low). */
    private Label matchBadge(double score) {
        String bg, fg;
        if (score >= 66) { bg = "-accent-success-bg"; fg = "-accent-success"; }
        else if (score >= 33) { bg = "-accent-amber-bg"; fg = "-accent-amber"; }
        else { bg = "-luna-lightest"; fg = "-luna-mid"; }

        Label badge = new Label(Math.round(score) + "% match");
        badge.setStyle("-fx-background-color: " + bg + "; -fx-text-fill: " + fg + "; " +
            "-fx-padding: 4 11; -fx-background-radius: 999; -fx-font-size: 11.5; -fx-font-weight: bold;");
        return badge;
    }

    private void showEmptyTopics() {
        showEmptyTopics("No recommendations right now. Join a group and post a few topics, then hit Refresh to get personalized suggestions.");
    }

    private void showEmptyTopics(String message) {
        topicsBox.getChildren().clear();
        Label empty = new Label(message);
        empty.getStyleClass().add("muted-text");
        empty.setWrapText(true);
        empty.setStyle("-fx-padding: 24 20;");
        topicsBox.getChildren().add(empty);
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
            System.err.println("[Recommend] Error opening topic: " + e.getMessage());
        }
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Desktop Client");
        } catch (Exception e) {
            System.err.println("[Recommend] Error going back: " + e.getMessage());
        }
    }

    private String get(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            if (conn.getResponseCode() != 200) return null;
            BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
            StringBuilder response = new StringBuilder();
            String line;
            while ((line = in.readLine()) != null) response.append(line);
            in.close();
            return response.toString();
        } catch (Exception e) {
            System.err.println("[Recommend] Request error: " + e.getMessage());
            return null;
        }
    }
}
