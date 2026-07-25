package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.WindowUtil;
import com.discussionhub.client.utils.AppConfig;
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

// Mirrors resources/views/quizzes/my-review.blade.php via the new
// GET /api/quiz/{id}/my-review endpoint (QuizEngineController::myReviewApi).
public class QuizReviewController {

    private static final String BASE_URL = AppConfig.BASE_URL;

    @FXML private Label syncStatusLabel;
    @FXML private Label titleLabel;
    @FXML private Label metaLabel;
    @FXML private VBox questionsBox;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService, int quizId) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("quizzes");
        load(quizId);
    }

    private void load(int quizId) {
        Label loading = new Label("Loading…");
        loading.getStyleClass().add("muted-text");
        questionsBox.getChildren().setAll(loading);

        new Thread(() -> {
            String body = get("/api/quiz/" + quizId + "/my-review");
            if (body == null) {
                Platform.runLater(() -> showMessage("Couldn't reach the server — check your connection."));
                return;
            }
            try {
                JSONObject json = new JSONObject(body);
                Platform.runLater(() -> render(json));
            } catch (Exception e) {
                System.err.println("[QuizReview] Parse error: " + e.getMessage());
                Platform.runLater(() -> showMessage("Couldn't load this review — please try again."));
            }
        }).start();
    }

    private void render(JSONObject json) {
        titleLabel.setText(json.optString("title", "Quiz") + " — Your Review");
        metaLabel.setText("Score: " + fmtNumber(json.optDouble("score", 0)) + "  ·  Submitted " + json.optString("submitted_at", ""));

        JSONArray questions = json.optJSONArray("questions");
        if (questions == null || questions.isEmpty()) {
            showMessage("No questions found for this quiz.");
            return;
        }

        VBox box = new VBox(14);
        for (int i = 0; i < questions.length(); i++) {
            box.getChildren().add(questionCard(questions.getJSONObject(i), i));
        }
        questionsBox.getChildren().setAll(box);
    }

    private VBox questionCard(JSONObject q, int index) {
        boolean isCorrect = q.optBoolean("is_correct", false);
        String accent = isCorrect ? "-accent-success" : "-accent-danger";

        VBox card = new VBox(8);
        card.getStyleClass().add("panel-card");
        card.setStyle("-fx-padding: 18 20; -fx-border-width: 3 1 1 1; -fx-border-color: " + accent + " -surface-border -surface-border -surface-border;");

        Label questionLabel = new Label("Q" + (index + 1) + ". " + q.optString("question_text", ""));
        questionLabel.getStyleClass().add("heading-text");
        questionLabel.setWrapText(true);
        questionLabel.setStyle("-fx-font-size: 14;");

        String yourAnswer = q.isNull("your_answer") ? "Not answered" : q.optString("your_answer", "Not answered");
        Label yourAnswerLine = new Label("Your answer: " + yourAnswer + (isCorrect ? "  ✓" : "  ✗"));
        yourAnswerLine.setWrapText(true);
        yourAnswerLine.setStyle("-fx-font-size: 12.5; -fx-font-weight: bold; -fx-text-fill: " + accent + ";");

        card.getChildren().addAll(questionLabel, yourAnswerLine);

        if (!isCorrect && !q.isNull("correct_answer")) {
            Label correctLine = new Label("Correct answer: " + q.optString("correct_answer", ""));
            correctLine.setWrapText(true);
            correctLine.setStyle("-fx-font-size: 12.5; -fx-font-weight: bold; -fx-text-fill: -accent-success;");
            card.getChildren().add(correctLine);
        }

        return card;
    }

    private String fmtNumber(double m) {
        return (m == Math.floor(m)) ? String.valueOf((int) m) : String.valueOf(m);
    }

    private void showMessage(String message) {
        Label empty = new Label(message);
        empty.getStyleClass().add("muted-text");
        empty.setWrapText(true);
        questionsBox.getChildren().setAll(empty);
    }

    @FXML
    private void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("quizzes-view.fxml"));
            Scene scene = new Scene(loader.load());
            QuizzesController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Quizzes");
        } catch (Exception e) {
            System.err.println("[QuizReview] Error going back: " + e.getMessage());
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
            System.err.println("[QuizReview] Request error: " + e.getMessage());
            return null;
        }
    }
}
