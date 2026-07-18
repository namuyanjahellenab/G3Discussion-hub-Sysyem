package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Label;
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

// Backs the "list of things" screens (My Questions, Marks) that share
// simple-list-view.fxml - each is a thin wrapper around one GET to the
// matching /api/* endpoint added for the desktop client, rendered with the
// same card styling as the Dashboard. Quizzes and Recommend each outgrew
// this into their own dedicated screen (QuizzesController, RecommendController).
public class SimpleListController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    @FXML private Label titleLabel;
    @FXML private Label syncStatusLabel;
    @FXML private VBox contentBox;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        boolean isOnline = NetworkUtil.isNetworkAvailable();
        syncStatusLabel.setText(isOnline ? "● ONLINE" : "● OFFLINE");
        syncStatusLabel.setStyle("-fx-text-fill: " + (isOnline ? "#90ee90" : "#ffcc00") + "; -fx-font-size: 12; -fx-font-weight: bold;");
    }

    // ---- Screen entry points -------------------------------------------

    public void loadMyQuestions() {
        sidebarController.setActive("myquestions");
        titleLabel.setText("My Questions");
        showLoading();
        fetchAsync("/api/my-questions", json -> {
            JSONArray topics = json.getJSONArray("data");
            if (topics.isEmpty()) {
                showEmpty("You haven't asked any questions yet.");
                return;
            }
            for (int i = 0; i < topics.length(); i++) {
                JSONObject t = topics.getJSONObject(i);
                VBox card = card();
                card.getChildren().add(heading(t.getString("title")));
                card.getChildren().add(meta((t.optString("group_name", "General"))
                    + "  ·  " + t.getInt("reply_count") + " replies"
                    + "  ·  " + t.optString("created_at", "")));
                if (!t.isNull("accepted_reply")) {
                    JSONObject accepted = t.getJSONObject("accepted_reply");
                    card.getChildren().add(acceptedAnswerBox(accepted.getString("content"), accepted.getString("author_name")));
                } else if (t.getBoolean("has_unread_answer")) {
                    card.getChildren().add(pill("New answer", "#3F9C6B", "#E4F4EC"));
                }
                contentBox.getChildren().add(card);
            }
        });
    }

    // ---- Small styled building blocks, matching the Dashboard's look ---

    private VBox card() {
        VBox card = new VBox(10);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 10; -fx-padding: 16 18; " +
            "-fx-effect: dropshadow(gaussian, #e3e6ee, 6, 0, 0, 2);");
        return card;
    }

    private Label heading(String text) {
        Label l = new Label(text);
        l.setWrapText(true);
        l.setStyle("-fx-font-weight: bold; -fx-font-size: 14; -fx-text-fill: #011C40;");
        return l;
    }

    private Label meta(String text) {
        Label l = new Label(text);
        l.setStyle("-fx-text-fill: #6B8094; -fx-font-size: 11.5;");
        return l;
    }

    private Label pill(String text, String fg, String bg) {
        Label l = new Label(text);
        l.setStyle("-fx-background-color: " + bg + "; -fx-text-fill: " + fg + "; " +
            "-fx-padding: 3 10; -fx-background-radius: 10; -fx-font-size: 11; -fx-font-weight: bold;");
        return l;
    }

    private VBox acceptedAnswerBox(String content, String authorName) {
        VBox box = new VBox(4);
        box.setStyle("-fx-background-color: #E4F4EC; -fx-background-radius: 6; -fx-padding: 10 12;");
        Label badge = new Label("✔ Accepted answer — " + authorName);
        badge.setStyle("-fx-text-fill: #3F9C6B; -fx-font-weight: bold; -fx-font-size: 11.5;");
        Label body = new Label(content);
        body.setWrapText(true);
        body.setStyle("-fx-text-fill: #33455A; -fx-font-size: 12;");
        box.getChildren().addAll(badge, body);
        return box;
    }

    private void showLoading() {
        contentBox.getChildren().clear();
        Label loading = new Label("Loading…");
        loading.setStyle("-fx-text-fill: #6B8094; -fx-font-size: 13;");
        contentBox.getChildren().add(loading);
    }

    private void showEmpty(String message) {
        Platform.runLater(() -> {
            contentBox.getChildren().clear();
            Label empty = new Label(message);
            empty.setStyle("-fx-text-fill: #6B8094; -fx-font-size: 13;");
            contentBox.getChildren().add(empty);
        });
    }

    // ---- HTTP helpers ----------------------------------------------------

    private interface ObjectHandler { void handle(JSONObject json); }

    private void fetchAsync(String path, ObjectHandler handler) {
        new Thread(() -> {
            String body = get(path);
            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    dbManager.cacheApiResponse(path, body);
                    Platform.runLater(() -> {
                        contentBox.getChildren().clear();
                        handler.handle(json);
                        if (contentBox.getChildren().isEmpty()) showEmpty("Nothing here yet.");
                    });
                    return;
                } catch (Exception e) {
                    System.err.println("[SimpleList] Parse error: " + e.getMessage());
                }
            }

            String cached = dbManager.getCachedApiResponse(path);
            if (cached != null) {
                try {
                    JSONObject json = new JSONObject(cached);
                    Platform.runLater(() -> {
                        contentBox.getChildren().clear();
                        handler.handle(json);
                        if (contentBox.getChildren().isEmpty()) showEmpty("Nothing here yet.");
                        markOffline();
                    });
                    return;
                } catch (Exception e) {
                    System.err.println("[SimpleList] Cached response parse error: " + e.getMessage());
                }
            }
            showEmpty("Couldn't reach the server — check your connection.");
        }).start();
    }

    private void markOffline() {
        syncStatusLabel.setText("● OFFLINE — showing saved data");
        syncStatusLabel.setStyle("-fx-text-fill: #ffcc00; -fx-font-size: 12; -fx-font-weight: bold;");
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
            System.err.println("[SimpleList] Request error: " + e.getMessage());
            return null;
        }
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) titleLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Desktop Client");
        } catch (Exception e) {
            System.err.println("[SimpleList] Error going back: " + e.getMessage());
        }
    }
}
