package com.discussionhub.client;

import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import javafx.util.Duration;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class QuizModalController {

    @FXML private Label quizTitleLabel;
    @FXML private Label timerLabel;
    @FXML private Label questionCountLabel;
    @FXML private Label questionTextLabel;
    @FXML private VBox answersContainer;
    @FXML private Button prevButton;
    @FXML private Button nextButton;

    private String quizId;
    private List<String> questions = new ArrayList<>();
    private List<String[]> options = new ArrayList<>();
    private Map<Integer, Integer> selectedAnswers = new HashMap<>();
    private int currentQuestionIndex = 0;
    private int remainingSeconds;
    private Timeline countdownTimeline;
    private boolean isAutoSubmit = false;

    public void setQuizData(String quizId, String title, List<String> questions, List<String[]> options, int durationMinutes) {
        this.quizId = quizId;
        this.quizTitleLabel.setText(title);
        this.questions = questions;
        this.options = options;
        this.remainingSeconds = durationMinutes * 60;

        startCountdown();
        displayQuestion(0);
    }

    private void startCountdown() {
        countdownTimeline = new Timeline(new KeyFrame(Duration.seconds(1), e -> {
            remainingSeconds--;
            int mins = remainingSeconds / 60;
            int secs = remainingSeconds % 60;
            timerLabel.setText(String.format("%02d:%02d", mins, secs));

            if (remainingSeconds <= 60) {
                timerLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 26; -fx-text-fill: #d32f2f;");
            }

            if (remainingSeconds <= 0) {
                stopCountdown();
                autoSubmit();
            }
        }));
        countdownTimeline.setCycleCount(Timeline.INDEFINITE);
        countdownTimeline.play();
    }

    private void displayQuestion(int index) {
        currentQuestionIndex = index;
        questionCountLabel.setText("Question " + (index + 1) + " of " + questions.size());
        questionTextLabel.setText(questions.get(index));
        answersContainer.getChildren().clear();

        ToggleGroup group = new ToggleGroup();
        String[] opts = options.get(index);
        for (int i = 0; i < opts.length; i++) {
            RadioButton rb = new RadioButton(opts[i]);
            rb.setToggleGroup(group);
            rb.setWrapText(true);
            rb.setStyle("-fx-font-size: 13; -fx-padding: 8; -fx-background-color: white;");
            
            final int optionIndex = i;
            if (selectedAnswers.getOrDefault(index, -1) == optionIndex) {
                rb.setSelected(true);
            }

            rb.selectedProperty().addListener((obs, oldVal, newVal) -> {
                if (newVal) {
                    selectedAnswers.put(currentQuestionIndex, optionIndex);
                    rb.setStyle("-fx-font-size: 13; -fx-padding: 8; -fx-background-color: #e8eef7; -fx-border-color: #1a73e8; -fx-border-radius: 4;");
                } else {
                    rb.setStyle("-fx-font-size: 13; -fx-padding: 8; -fx-background-color: white;");
                }
            });
            
            answersContainer.getChildren().add(rb);
        }

        prevButton.setDisable(index == 0);
        nextButton.setDisable(index == questions.size() - 1);
    }

    @FXML
    private void onPrevious() {
        displayQuestion(currentQuestionIndex - 1);
    }

    @FXML
    private void onNext() {
        displayQuestion(currentQuestionIndex + 1);
    }

    @FXML
    private void onSubmitQuiz() {
        submitQuiz(false);
    }

    private void autoSubmit() {
        isAutoSubmit = true;
        submitQuiz(true);
    }

    private void submitQuiz(boolean auto) {
        stopCountdown();
        
        // Build JSON manually
        StringBuilder answersJson = new StringBuilder("{");
        for (Map.Entry<Integer, Integer> entry : selectedAnswers.entrySet()) {
            if (answersJson.length() > 1) answersJson.append(",");
            answersJson.append("\"").append(entry.getKey()).append("\":").append(entry.getValue());
        }
        answersJson.append("}");

        String payload = String.format("{\"quizId\":\"%s\",\"answers\":%s,\"isAutoSubmit\":%b}", 
                                       quizId, answersJson.toString(), auto);

        new Thread(() -> {
            try {
                URL url = new URL("http://localhost:8000/api/quiz/submit");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                try (OutputStream os = conn.getOutputStream()) {
                    byte[] input = payload.getBytes(StandardCharsets.UTF_8);
                    os.write(input, 0, input.length);
                }

                int code = conn.getResponseCode();
                Platform.runLater(() -> {
                    Alert alert = new Alert(Alert.AlertType.INFORMATION);
                    alert.setTitle("Quiz Submission");
                    if (code == 200 || code == 201) {
                        alert.setHeaderText("Success");
                        alert.setContentText(auto ? "Time's up! Your quiz was automatically submitted." : "Submitted successfully!");
                    } else {
                        alert.setHeaderText("Error");
                        alert.setContentText("Failed to submit quiz. Server returned " + code);
                    }
                    alert.showAndWait();
                    closeModal();
                });
            } catch (Exception e) {
                Platform.runLater(() -> {
                    Alert alert = new Alert(Alert.AlertType.ERROR);
                    alert.setContentText("Error submitting quiz: " + e.getMessage());
                    alert.showAndWait();
                    closeModal();
                });
            }
        }).start();
    }

    private void stopCountdown() {
        if (countdownTimeline != null) {
            countdownTimeline.stop();
        }
    }

    private void closeModal() {
        Stage stage = (Stage) quizTitleLabel.getScene().getWindow();
        stage.close();
    }
}
