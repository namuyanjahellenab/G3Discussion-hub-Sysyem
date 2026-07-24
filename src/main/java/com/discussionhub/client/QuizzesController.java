package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import com.discussionhub.client.utils.WindowUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Button;
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

// Mirrors resources/views/quizzes/index.blade.php + its API twin
// StudentDataApiController::quizzes(): stat cards (Available/Upcoming/
// Completed/Total Score), 4 tabs (Available/Upcoming/Completed/Missed),
// Available rows are clickable straight into the quiz, and Completed rows
// get a "Review answers" button. Previously this screen was just
// SimpleListController.loadQuizzes() - four plain sections with inert
// labels and no click-through at all (the 15s popup was the only way in).
public class QuizzesController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    @FXML private Label syncStatusLabel;
    @FXML private Label availableCountLabel;
    @FXML private Label upcomingCountLabel;
    @FXML private Label completedCountLabel;
    @FXML private Label totalScoreLabel;
    @FXML private Button availableTabBtn;
    @FXML private Button upcomingTabBtn;
    @FXML private Button completedTabBtn;
    @FXML private Button missedTabBtn;
    @FXML private VBox tabContentBox;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private JSONObject lastData;
    private String activeTab = "available";

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("quizzes");

        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
        userRoleLabel.setText((SessionManager.role == null || SessionManager.role.isBlank() ? "Student" : SessionManager.role) + " Account");

        load();
    }

    private void load() {
        tabContentBox.getChildren().setAll(loadingLabel());
        new Thread(() -> {
            String body = get("/api/quizzes");
            if (body == null) {
                Platform.runLater(() -> showMessage("Couldn't reach the server — check your connection."));
                return;
            }
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> {
                    lastData = json;
                    availableCountLabel.setText(String.valueOf(json.getJSONArray("available").length()));
                    upcomingCountLabel.setText(String.valueOf(json.getJSONArray("upcoming").length()));
                    completedCountLabel.setText(String.valueOf(json.getJSONArray("completed").length()));
                    totalScoreLabel.setText(fmtNumber(json.optDouble("total_score", 0)));
                    selectTab("available");
                });
            } catch (Exception e) {
                System.err.println("[Quizzes] Parse error: " + e.getMessage());
                Platform.runLater(() -> showMessage("Couldn't load quizzes — please try again."));
            }
        }).start();
    }

    @FXML
    private void onAvailableTab() { selectTab("available"); }

    @FXML
    private void onUpcomingTab() { selectTab("upcoming"); }

    @FXML
    private void onCompletedTab() { selectTab("completed"); }

    @FXML
    private void onMissedTab() { selectTab("missed"); }

    private void selectTab(String tab) {
        activeTab = tab;
        applyTabButtonStyles();
        if (lastData == null) return;

        switch (tab) {
            case "available" -> renderAvailable(lastData.getJSONArray("available"));
            case "upcoming" -> renderUpcoming(lastData.getJSONArray("upcoming"));
            case "completed" -> renderCompleted(lastData.getJSONArray("completed"));
            case "missed" -> renderMissed(lastData.getJSONArray("missed"));
        }
    }

    private void applyTabButtonStyles() {
        style(availableTabBtn, "available".equals(activeTab));
        style(upcomingTabBtn, "upcoming".equals(activeTab));
        style(completedTabBtn, "completed".equals(activeTab));
        style(missedTabBtn, "missed".equals(activeTab));
    }

    private void style(Button btn, boolean active) {
        btn.setStyle("-fx-background-color: transparent; -fx-padding: 8 4; -fx-font-size: 13; -fx-cursor: hand; " +
            "-fx-border-color: transparent transparent " + (active ? "-luna-mid" : "transparent") + " transparent; " +
            "-fx-border-width: 0 0 2 0; -fx-text-fill: " + (active ? "-luna-mid" : "-text-muted") + "; " +
            "-fx-font-weight: " + (active ? "bold" : "normal") + ";");
    }

    private void renderAvailable(JSONArray quizzes) {
        if (quizzes.isEmpty()) { showMessage("No quizzes available right now."); return; }
        VBox box = new VBox();
        for (int i = 0; i < quizzes.length(); i++) {
            JSONObject q = quizzes.getJSONObject(i);
            box.getChildren().add(quizRow(q.getString("title"), "Active now",
                pill("Take now", "-accent-success"), true, q.getInt("id")));
        }
        tabContentBox.getChildren().setAll(box);
    }

    private void renderUpcoming(JSONArray quizzes) {
        if (quizzes.isEmpty()) { showMessage("No upcoming quizzes."); return; }
        VBox box = new VBox();
        for (int i = 0; i < quizzes.length(); i++) {
            JSONObject q = quizzes.getJSONObject(i);
            box.getChildren().add(quizRow(q.getString("title"), "Starts " + q.optString("starts_in", ""),
                pill("Upcoming", "-text-muted"), false, -1));
        }
        tabContentBox.getChildren().setAll(box);
    }

    private void renderMissed(JSONArray quizzes) {
        if (quizzes.isEmpty()) { showMessage("No missed quizzes."); return; }
        VBox box = new VBox();
        for (int i = 0; i < quizzes.length(); i++) {
            JSONObject q = quizzes.getJSONObject(i);
            box.getChildren().add(quizRow(q.getString("title"), "Closed " + q.optString("closed_ago", ""),
                pill("Missed", "-accent-danger"), false, -1));
        }
        tabContentBox.getChildren().setAll(box);
    }

    private void renderCompleted(JSONArray results) {
        if (results.isEmpty()) { showMessage("You haven't completed any quizzes yet."); return; }
        VBox box = new VBox();
        for (int i = 0; i < results.length(); i++) {
            JSONObject r = results.getJSONObject(i);
            int quizId = r.getInt("quiz_id");

            HBox row = new HBox(10);
            row.setAlignment(Pos.CENTER_LEFT);
            row.setStyle("-fx-padding: 12 4; -fx-border-color: transparent transparent -surface-border transparent; -fx-border-width: 0 0 1 0;");

            VBox main = new VBox(2);
            HBox.setHgrow(main, Priority.ALWAYS);
            Label title = new Label(r.getString("title"));
            title.getStyleClass().add("heading-text");
            title.setStyle("-fx-font-size: 13.5;");
            Label meta = new Label("Score: " + fmtNumber(r.optDouble("score", 0)) + "  ·  " + r.optString("submitted_ago", ""));
            meta.getStyleClass().add("muted-text");
            meta.setStyle("-fx-font-size: 12;");
            main.getChildren().addAll(title, meta);

            Button reviewBtn = new Button("Review answers");
            reviewBtn.setStyle("-fx-background-color: transparent; -fx-border-color: -luna-mid; -fx-text-fill: -luna-mid; " +
                "-fx-border-radius: 6; -fx-background-radius: 6; -fx-font-size: 11.5; -fx-font-weight: bold; -fx-padding: 6 14; -fx-cursor: hand;");
            reviewBtn.setOnAction(e -> openReview(quizId));

            row.getChildren().addAll(main, reviewBtn);
            box.getChildren().add(row);
        }
        tabContentBox.getChildren().setAll(box);
    }

    private HBox quizRow(String title, String metaText, Label pill, boolean clickable, int quizId) {
        HBox row = new HBox(10);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-padding: 12 4; -fx-border-color: transparent transparent -surface-border transparent; -fx-border-width: 0 0 1 0;"
            + (clickable ? " -fx-cursor: hand;" : ""));

        VBox main = new VBox(2);
        HBox.setHgrow(main, Priority.ALWAYS);
        Label titleLabel = new Label(title);
        titleLabel.getStyleClass().add("heading-text");
        titleLabel.setStyle("-fx-font-size: 13.5;");
        Label metaLabel = new Label(metaText);
        metaLabel.getStyleClass().add("muted-text");
        metaLabel.setStyle("-fx-font-size: 12;");
        main.getChildren().addAll(titleLabel, metaLabel);

        row.getChildren().addAll(main, pill);
        if (clickable) {
            row.setOnMouseClicked(e -> startQuiz(quizId));
        }
        return row;
    }

    private Label pill(String text, String fg) {
        Label l = new Label(text);
        l.setStyle("-fx-text-fill: " + fg + "; -fx-background-color: derive(" + fg + ", 85%); " +
            "-fx-font-size: 11; -fx-font-weight: bold; -fx-padding: 4 12; -fx-background-radius: 999;");
        return l;
    }

    private void startQuiz(int quizId) {
        Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
        QuizTakeController.open(stage, dbManager, syncService, quizId);
    }

    private void openReview(int quizId) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("quiz-review-view.fxml"));
            Scene scene = new Scene(loader.load());
            QuizReviewController controller = loader.getController();
            controller.setServices(dbManager, syncService, quizId);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Quiz Review");
        } catch (Exception e) {
            System.err.println("[Quizzes] Error opening review: " + e.getMessage());
        }
    }

    private String fmtNumber(double m) {
        return (m == Math.floor(m)) ? String.valueOf((int) m) : String.valueOf(m);
    }

    private Label loadingLabel() {
        Label loading = new Label("Loading…");
        loading.getStyleClass().add("muted-text");
        loading.setStyle("-fx-padding: 16 4;");
        return loading;
    }

    private void showMessage(String message) {
        Label empty = new Label(message);
        empty.getStyleClass().add("muted-text");
        empty.setWrapText(true);
        empty.setStyle("-fx-padding: 16 4;");
        tabContentBox.getChildren().setAll(empty);
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
            System.err.println("[Quizzes] Error going back: " + e.getMessage());
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
            System.err.println("[Quizzes] Request error: " + e.getMessage());
            return null;
        }
    }
}
