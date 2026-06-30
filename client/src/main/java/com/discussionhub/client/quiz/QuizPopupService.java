package com.discussionhub.client.quiz;

import javafx.application.Platform;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.effect.GaussianBlur;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.paint.Color;
import javafx.scene.text.Font;
import javafx.scene.text.FontWeight;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.stage.StageStyle;

import java.awt.Desktop;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.util.Timer;
import java.util.TimerTask;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Polls the Laravel quiz-engine endpoint for an active quiz and, when one is
 * found, blurs the main JavaFX window and shows a popup forcing the student
 * to start the quiz.
 *
 * Usage (call once from HelloApplication.start() after the main Stage is shown):
 *
 *     QuizPopupService.start(primaryStage);
 *
 * To stop polling (e.g. on logout or app close):
 *
 *     QuizPopupService.stop();
 */
public class QuizPopupService {

    // TODO: change to your real server address once deployed
    private static final String ACTIVE_QUIZ_URL = "http://127.0.0.1:8000/quiz/active-now";
    private static final String QUIZ_TAKE_BASE_URL = "http://127.0.0.1:8000/quiz/";

    private static final int POLL_INTERVAL_MS = 15000; // 15 seconds
    private static final int CONNECT_TIMEOUT_MS = 4000;
    private static final int READ_TIMEOUT_MS = 4000;

    private static Timer pollTimer;
    private static boolean popupShown = false;
    private static Stage mainStage;

    private QuizPopupService() {
        // static utility class
    }

    /** Begin polling for an active quiz. Call once, after primaryStage.show(). */
    public static void start(Stage stage) {
        mainStage = stage;
        popupShown = false;

        pollTimer = new Timer(true); // daemon thread, won't block app exit
        pollTimer.scheduleAtFixedRate(new TimerTask() {
            @Override
            public void run() {
                checkForActiveQuiz();
            }
        }, 0, POLL_INTERVAL_MS);
    }

    /** Stop polling (e.g. on logout). */
    public static void stop() {
        if (pollTimer != null) {
            pollTimer.cancel();
            pollTimer = null;
        }
        popupShown = false;
    }

    // ---- Polling -----------------------------------------------------

    private static void checkForActiveQuiz() {
        if (popupShown) return; // already showing, skip this poll

        try {
            String json = fetchActiveQuizJson();
            QuizInfo quiz = parseQuiz(json);

            if (quiz != null) {
                popupShown = true;
                Platform.runLater(() -> showQuizPopup(quiz));
            }
        } catch (Exception e) {
            // Network errors are expected when offline; fail silently and retry next poll
            System.out.println("QuizPopupService: poll failed - " + e.getMessage());
        }
    }

    private static String fetchActiveQuizJson() throws Exception {
        URL url = new URL(ACTIVE_QUIZ_URL);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("GET");
        conn.setConnectTimeout(CONNECT_TIMEOUT_MS);
        conn.setReadTimeout(READ_TIMEOUT_MS);
        conn.setRequestProperty("Accept", "application/json");
        conn.setRequestProperty("X-Requested-With", "XMLHttpRequest");

        // TODO: if the endpoint requires an auth/session cookie, attach it here
        // e.g. conn.setRequestProperty("Cookie", sessionCookie);

        int status = conn.getResponseCode();
        if (status != 200) {
            throw new RuntimeException("Unexpected response code: " + status);
        }

        StringBuilder sb = new StringBuilder();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
        }
        return sb.toString();
    }

    // ---- Minimal JSON parsing (no external library needed) -----------

    private static class QuizInfo {
        int quizId;
        String title;
        int durationMinutes;
    }

    /**
     * Parses the small fixed-shape JSON the endpoint returns, e.g.
     * {"quiz":{"QuizID":5,"Title":"week5 quiz","Duration":30, ...}}
     * Returns null if "quiz" is null or fields are missing.
     */
    private static QuizInfo parseQuiz(String json) {
        if (json == null || json.contains("\"quiz\":null")) {
            return null;
        }

        QuizInfo info = new QuizInfo();

        Matcher idMatch = Pattern.compile("\"QuizID\":(\\d+)").matcher(json);
        Matcher titleMatch = Pattern.compile("\"Title\":\"([^\"]*)\"").matcher(json);
        Matcher durationMatch = Pattern.compile("\"Duration\":(\\d+)").matcher(json);

        if (!idMatch.find() || !titleMatch.find()) {
            return null;
        }

        info.quizId = Integer.parseInt(idMatch.group(1));
        info.title = titleMatch.group(1);
        info.durationMinutes = durationMatch.find() ? Integer.parseInt(durationMatch.group(1)) : 0;

        return info;
    }

    // ---- UI: blur + popup ---------------------------------------------

    private static void showQuizPopup(QuizInfo quiz) {
        if (mainStage == null || mainStage.getScene() == null) return;

        // Blur the main window's content
        mainStage.getScene().getRoot().setEffect(new GaussianBlur(8));

        Stage popupStage = new Stage();
        popupStage.initOwner(mainStage);
        popupStage.initModality(Modality.WINDOW_MODAL);
        popupStage.initStyle(StageStyle.UNDECORATED);

        Label icon = new Label("\u23F0"); // clock emoji as a lightweight icon
        icon.setFont(Font.font(36));

        Label heading = new Label("Quiz Starting Now!");
        heading.setFont(Font.font("System", FontWeight.BOLD, 20));
        heading.setTextFill(Color.web("#0F172A"));

        Label titleLabel = new Label(quiz.title);
        titleLabel.setFont(Font.font("System", FontWeight.BOLD, 14));
        titleLabel.setTextFill(Color.web("#2563EB"));

        Label subLabel = new Label(quiz.durationMinutes + " minutes \u00B7 Please focus on your quiz");
        subLabel.setFont(Font.font(12));
        subLabel.setTextFill(Color.web("#64748B"));

        Button startButton = new Button("Start Quiz Now");
        startButton.setStyle(
            "-fx-background-color: #2563EB; -fx-text-fill: white; " +
            "-fx-font-size: 14px; -fx-font-weight: bold; " +
            "-fx-background-radius: 8; -fx-padding: 12 28;"
        );
        startButton.setOnMouseEntered(e -> startButton.setStyle(
            "-fx-background-color: #1D4ED8; -fx-text-fill: white; " +
            "-fx-font-size: 14px; -fx-font-weight: bold; " +
            "-fx-background-radius: 8; -fx-padding: 12 28;"
        ));
        startButton.setOnMouseExited(e -> startButton.setStyle(
            "-fx-background-color: #2563EB; -fx-text-fill: white; " +
            "-fx-font-size: 14px; -fx-font-weight: bold; " +
            "-fx-background-radius: 8; -fx-padding: 12 28;"
        ));

        startButton.setOnAction(e -> {
            openQuizInBrowser(quiz.quizId);
            closePopup(popupStage);
        });

        Label footnote = new Label("This quiz will auto-submit when time runs out");
        footnote.setFont(Font.font(10));
        footnote.setTextFill(Color.web("#94A3B8"));

        VBox card = new VBox(10, icon, heading, titleLabel, subLabel, startButton, footnote);
        card.setAlignment(Pos.CENTER);
        card.setStyle(
            "-fx-background-color: white; -fx-background-radius: 16; " +
            "-fx-padding: 36 32; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.3), 24, 0, 0, 8);"
        );
        card.setMaxWidth(320);

        StackPane root = new StackPane(card);
        root.setStyle("-fx-background-color: transparent;");
        root.setPadding(new javafx.geometry.Insets(40));

        Scene scene = new Scene(root);
        scene.setFill(Color.TRANSPARENT);
        popupStage.setScene(scene);

        // Center over the main window
        popupStage.setOnShown(e -> {
            popupStage.setX(mainStage.getX() + (mainStage.getWidth() - popupStage.getWidth()) / 2);
            popupStage.setY(mainStage.getY() + (mainStage.getHeight() - popupStage.getHeight()) / 2);
        });

        popupStage.show();
    }

    private static void closePopup(Stage popupStage) {
        if (mainStage != null && mainStage.getScene() != null) {
            mainStage.getScene().getRoot().setEffect(null);
        }
        popupStage.close();
        popupShown = false; // allow future quizzes to trigger again
    }

    private static void openQuizInBrowser(int quizId) {
        try {
            String url = QUIZ_TAKE_BASE_URL + quizId + "/take";
            if (Desktop.isDesktopSupported()) {
                Desktop.getDesktop().browse(URI.create(url));
            }
        } catch (Exception e) {
            System.out.println("QuizPopupService: failed to open browser - " + e.getMessage());
        }
    }
}