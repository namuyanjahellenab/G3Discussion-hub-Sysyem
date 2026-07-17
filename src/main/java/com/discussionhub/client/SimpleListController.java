package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Label;
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

// Backs the four "list of things" screens (My Questions, Marks, Quizzes,
// Recommend) that all share simple-list-view.fxml - each is a thin wrapper
// around one GET to the matching /api/* endpoint added for the desktop
// client, rendered with the same card styling as the Dashboard.
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

    // One card per group the student belongs to - matches web's
    // marks/index.blade.php, both backed by the same MarksService so
    // participation curving and quiz grouping can't drift between platforms.
    public void loadMarks() {
        sidebarController.setActive("marks");
        titleLabel.setText("Marks");
        showLoading();
        fetchAsync("/api/marks", json -> {
            JSONArray groups = json.getJSONArray("groups");
            if (groups.isEmpty()) {
                showEmpty("Join a group to start earning participation and quiz marks.");
                return;
            }

            for (int i = 0; i < groups.length(); i++) {
                contentBox.getChildren().add(groupMarksCard(groups.getJSONObject(i)));
            }
        });
    }

    private VBox groupMarksCard(JSONObject group) {
        VBox card = card();
        card.getChildren().add(heading(group.getString("group_name")));

        HBox stats = new HBox(24);
        stats.getChildren().addAll(
            statTile("Participation", group.get("participation") + " / 10"),
            statTile("Quiz Average", group.isNull("quiz_average") ? "—" : group.get("quiz_average") + "%"),
            statTile("Quizzes Taken", String.valueOf(group.getInt("quizzes_taken")))
        );
        card.getChildren().add(stats);
        card.getChildren().add(meta("Posts: " + group.getInt("post_count") + "  ·  Replies: " + group.getInt("reply_count")));

        JSONArray quizzes = group.getJSONArray("quizzes");
        for (int i = 0; i < quizzes.length(); i++) {
            JSONObject r = quizzes.getJSONObject(i);
            VBox row = new VBox(2);
            row.setStyle("-fx-padding: 8 0; -fx-border-color: transparent transparent #E1E9ED transparent; -fx-border-width: 0 0 1 0;");

            HBox topRow = new HBox(10);
            topRow.setAlignment(Pos.CENTER_LEFT);
            Label quizLabel = new Label(r.optString("title", "Quiz #" + r.getInt("quiz_id")));
            quizLabel.setStyle("-fx-font-size: 13; -fx-font-weight: bold; -fx-text-fill: #33455A;");
            Region spacer = new Region();
            HBox.setHgrow(spacer, Priority.ALWAYS);
            Label scoreLabel = new Label(r.get("score") + " pts");
            scoreLabel.setStyle("-fx-font-weight: bold; -fx-text-fill: #26658C;");
            topRow.getChildren().addAll(quizLabel, spacer, scoreLabel);

            String metaText = r.optString("submitted_at", "")
                + (r.optBoolean("auto_submitted", false) ? "  ·  auto-submitted" : "");
            row.getChildren().addAll(topRow, meta(metaText));
            card.getChildren().add(row);
        }

        return card;
    }

    public void loadRecommend() {
        sidebarController.setActive("recommend");
        titleLabel.setText("Recommend");
        showLoading();
        fetchAsyncArray("/api/recommend", topics -> {
            if (topics.isEmpty()) {
                showEmpty("No recommendations right now. Join a group and post a few topics, then check back for personalized suggestions.");
                return;
            }
            for (int i = 0; i < topics.length(); i++) {
                JSONObject t = topics.getJSONObject(i);
                double score = t.optDouble("relevance_score", 0);

                VBox card = card();
                card.setStyle(card.getStyle() + " -fx-cursor: hand;");
                card.setOnMouseClicked(e -> openTopic(t.getInt("id"), t.getString("title")));

                HBox topRow = new HBox(10);
                topRow.setAlignment(Pos.CENTER_LEFT);
                Label titleLabel = heading(t.getString("title"));
                HBox.setHgrow(titleLabel, Priority.ALWAYS);
                topRow.getChildren().addAll(titleLabel, matchBadge(score));

                String metaText = t.optString("category", "General")
                    + "  ·  " + t.optString("creator_name", "a member")
                    + "  ·  " + t.optString("group_name", "General")
                    + "  ·  " + t.optString("created_at", "");

                card.getChildren().addAll(topRow, meta(metaText));
                contentBox.getChildren().add(card);
            }
        });
    }

    /** Matches recommend/index.blade.php's .match-badge tiers (high/medium/low). */
    private Label matchBadge(double score) {
        String bg, fg;
        if (score >= 66) { bg = "#E4F4EC"; fg = "#3F9C6B"; }
        else if (score >= 33) { bg = "#FBF0E1"; fg = "#D98E3D"; }
        else { bg = "#EAF1F4"; fg = "#26658C"; }

        Label badge = new Label(Math.round(score) + "% match");
        badge.setStyle("-fx-background-color: " + bg + "; -fx-text-fill: " + fg + "; " +
            "-fx-padding: 4 11; -fx-background-radius: 999; -fx-font-size: 11.5; -fx-font-weight: bold;");
        return badge;
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
            System.err.println("[SimpleList] Error opening topic: " + e.getMessage());
        }
    }

    public void loadQuizzes() {
        sidebarController.setActive("quizzes");
        titleLabel.setText("Quizzes");
        showLoading();
        fetchAsync("/api/quizzes", json -> {
            addQuizSection("Available Now", json.getJSONArray("available"), "#3F9C6B", "#E4F4EC");
            addQuizSection("Upcoming", json.getJSONArray("upcoming"), "#D98E3D", "#FBF0E1");
            addQuizSection("Missed", json.getJSONArray("missed"), "#D9483D", "#FBE7E5");

            JSONArray completed = json.getJSONArray("completed");
            if (completed.isEmpty()) return;
            VBox card = card();
            card.getChildren().add(heading("Completed"));
            for (int i = 0; i < completed.length(); i++) {
                JSONObject q = completed.getJSONObject(i);
                HBox row = new HBox(10);
                row.setAlignment(Pos.CENTER_LEFT);
                Label t = new Label(q.getString("title"));
                t.setStyle("-fx-font-size: 13; -fx-text-fill: #33455A;");
                Region spacer = new Region();
                HBox.setHgrow(spacer, Priority.ALWAYS);
                Label score = new Label(q.getInt("score") + "%");
                score.setStyle("-fx-font-weight: bold; -fx-text-fill: #26658C;");
                row.getChildren().addAll(t, spacer, score);
                card.getChildren().add(row);
            }
            contentBox.getChildren().add(card);

            if (contentBox.getChildren().isEmpty()) showEmpty("No quizzes yet.");
        });
    }

    private void addQuizSection(String title, JSONArray quizzes, String fg, String bg) {
        if (quizzes.isEmpty()) return;
        VBox card = card();
        card.getChildren().add(heading(title));
        for (int i = 0; i < quizzes.length(); i++) {
            JSONObject q = quizzes.getJSONObject(i);
            HBox row = new HBox(10);
            row.setAlignment(Pos.CENTER_LEFT);
            Label t = new Label(q.getString("title"));
            t.setStyle("-fx-font-size: 13; -fx-text-fill: #33455A;");
            Region spacer = new Region();
            HBox.setHgrow(spacer, Priority.ALWAYS);
            Label when = new Label(q.optString("start_time", "") + "  (" + q.getInt("duration_minutes") + " min)");
            when.setStyle("-fx-text-fill: #6B8094; -fx-font-size: 11.5;");
            row.getChildren().addAll(t, spacer, when);
            card.getChildren().add(row);
        }
        contentBox.getChildren().add(card);
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

    private VBox statTile(String label, String value) {
        VBox tile = new VBox(4);
        tile.setAlignment(Pos.CENTER_LEFT);
        Label valueLabel = new Label(value);
        valueLabel.setStyle("-fx-font-size: 20; -fx-font-weight: bold; -fx-text-fill: #26658C;");
        Label captionLabel = new Label(label);
        captionLabel.setStyle("-fx-text-fill: #6B8094; -fx-font-size: 11.5;");
        tile.getChildren().addAll(valueLabel, captionLabel);
        return tile;
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
    private interface ArrayHandler { void handle(JSONArray json); }

    private void fetchAsync(String path, ObjectHandler handler) {
        new Thread(() -> {
            String body = get(path);
            if (body == null) { showEmpty("Couldn't reach the server — check your connection."); return; }
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> {
                    contentBox.getChildren().clear();
                    handler.handle(json);
                    if (contentBox.getChildren().isEmpty()) showEmpty("Nothing here yet.");
                });
            } catch (Exception e) {
                System.err.println("[SimpleList] Parse error: " + e.getMessage());
                showEmpty("Couldn't load this — please try again.");
            }
        }).start();
    }

    private void fetchAsyncArray(String path, ArrayHandler handler) {
        new Thread(() -> {
            String body = get(path);
            if (body == null) { showEmpty("Couldn't reach the server — check your connection."); return; }
            try {
                JSONArray json = new JSONArray(body);
                Platform.runLater(() -> {
                    contentBox.getChildren().clear();
                    handler.handle(json);
                });
            } catch (Exception e) {
                System.err.println("[SimpleList] Parse error: " + e.getMessage());
                showEmpty("Couldn't load this — please try again.");
            }
        }).start();
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
