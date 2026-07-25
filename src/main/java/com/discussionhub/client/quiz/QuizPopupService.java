package com.discussionhub.client.quiz;

import com.discussionhub.client.QuizTakeController;
import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.AppConfig;
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
 * Usage (call once after login, with the session's real auth token):
 *
 *     QuizPopupService.start(stage, SessionManager.token, dbManager, syncService);
 *
 * To stop polling (e.g. on logout or app close):
 *
 *     QuizPopupService.stop();
 */
public class QuizPopupService {

    private static final String BASE_URL = AppConfig.BASE_URL;
    private static final String ACTIVE_QUIZ_URL = BASE_URL + "/api/quiz/active-now";

    private static String authToken = null;

    private static final int POLL_INTERVAL_MS = 15000; // 15 seconds
    private static final int CONNECT_TIMEOUT_MS = 4000;
    private static final int READ_TIMEOUT_MS = 4000;

    private static Timer pollTimer;
    private static boolean popupShown = false;
    private static Stage mainStage;
    private static DatabaseManager dbManager;
    private static DeltaSyncService syncService;
    private static Integer lastShownQuizId = null;
    private static Stage currentPopupStage;
    private static javafx.animation.PauseTransition popupCloseTimer;

    private QuizPopupService() {
        // static utility class
    }

    /**
     * Begin polling for an active quiz using an already-authenticated session token
     * (e.g. SessionManager.token after login). dbManager/syncService are threaded
     * through to QuizTakeController so "Back to Dashboard" works after a quiz
     * started from this popup, same as a quiz started from the Quizzes list.
     */
    public static void start(Stage stage, String token, DatabaseManager dbManager, DeltaSyncService syncService) {
        authToken = token;
        mainStage = stage;
        QuizPopupService.dbManager = dbManager;
        QuizPopupService.syncService = syncService;
        popupShown = false;

        if (pollTimer != null) {
            pollTimer.cancel(); // guard against double-start
        }

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
        authToken = null;
    }

    // ---- Polling -----------------------------------------------------
    private static void checkForActiveQuiz() {
        try {
            if (authToken == null) {
                // Session token was rejected/expired and we have no way to refresh
                // it ourselves - stop polling until the app restarts / re-logs in.
                System.out.println("QuizPopupService: session token missing, stopping poll");
                stop();
                return;
            }

            String json = fetchActiveQuizJson();
            QuizInfo quiz = parseQuiz(json);

            if (popupShown) {
                // Keep polling even while shown (rather than the old
                // skip-if-shown guard) purely to notice the moment the quiz's
                // time window (StartTime + Duration) lapses server-side - the
                // popup must vanish immediately instead of sitting there
                // blocking the window for a quiz that's no longer joinable.
                if (quiz == null) {
                    Platform.runLater(QuizPopupService::closeExpiredPopup);
                }
                return;
            }

            boolean isNewQuiz = quiz != null
                    && (lastShownQuizId == null || lastShownQuizId != quiz.quizId);

            if (isNewQuiz) {
                popupShown = true;
                lastShownQuizId = quiz.quizId;
                Platform.runLater(() -> showQuizPopup(quiz));
            }
        } catch (UnauthorizedException e) {
            System.out.println("QuizPopupService: token rejected");
            authToken = null; // token was rejected/expired; stop() on the next poll
        } catch (Exception e) {
            // Network errors are expected when offline; fail silently and retry next poll
            System.out.println("QuizPopupService: poll failed - " + e.getMessage());
        }
    }

    /** Simple marker exception so we can distinguish a 401 from other failures. */
    private static class UnauthorizedException extends Exception {
        UnauthorizedException(String msg) { super(msg); }
    }

    private static String fetchActiveQuizJson() throws Exception {
        URL url = new URL(ACTIVE_QUIZ_URL);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("GET");
        conn.setConnectTimeout(CONNECT_TIMEOUT_MS);
        conn.setReadTimeout(READ_TIMEOUT_MS);
        conn.setRequestProperty("Accept", "application/json");
        conn.setRequestProperty("X-Requested-With", "XMLHttpRequest");
        conn.setRequestProperty("Authorization", "Bearer " + authToken);

        int status = conn.getResponseCode();
        if (status == 401) {
            throw new UnauthorizedException("Token rejected");
        }
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
        long expiryEpochMillis; // StartTime + Duration, precomputed; 0 if StartTime was unparseable
    }

    /**
     * Parses the small fixed-shape JSON the endpoint returns, e.g.
     * {"quiz":{"QuizID":5,"Title":"week5 quiz","Duration":30,"StartTime":"2026-07-23T09:28:20.000000Z", ...}}
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
        Matcher startTimeMatch = Pattern.compile("\"StartTime\":\"([^\"]*)\"").matcher(json);

        if (!idMatch.find() || !titleMatch.find()) {
            return null;
        }

        info.quizId = Integer.parseInt(idMatch.group(1));
        info.title = titleMatch.group(1);
        info.durationMinutes = durationMatch.find() ? Integer.parseInt(durationMatch.group(1)) : 0;

        if (startTimeMatch.find()) {
            try {
                info.expiryEpochMillis = java.time.Instant.parse(startTimeMatch.group(1)).toEpochMilli()
                        + (long) info.durationMinutes * 60_000L;
            } catch (Exception ex) {
                info.expiryEpochMillis = 0; // falls back to poll-only close detection
            }
        }

        return info;
    }

    // ---- UI: blur + popup ---------------------------------------------

    private static void showQuizPopup(QuizInfo quiz) {
        if (mainStage == null || mainStage.getScene() == null) return;

        // Blur the main window's content
        mainStage.getScene().getRoot().setEffect(new GaussianBlur(8));

        Stage popupStage = new Stage();
        currentPopupStage = popupStage;
        popupStage.initOwner(mainStage);
        popupStage.initModality(Modality.WINDOW_MODAL);
        popupStage.initStyle(StageStyle.UNDECORATED);

        Label icon = new Label("\u23F0"); // clock emoji as a lightweight icon
        icon.setFont(Font.font(36));

        Label heading = new Label("Quiz Starting Now!");
        heading.setFont(Font.font("System", FontWeight.BOLD, 20));
        heading.setTextFill(Color.web("#011C40"));

        Label titleLabel = new Label(quiz.title);
        titleLabel.setFont(Font.font("System", FontWeight.BOLD, 14));
        titleLabel.setTextFill(Color.web("#26658C"));

        Label subLabel = new Label(quiz.durationMinutes + " minutes \u00B7 Please focus on your quiz");
        subLabel.setFont(Font.font(12));
        subLabel.setTextFill(Color.web("#6B8094"));

        Button startButton = new Button("Start Quiz Now");
        startButton.setStyle(
            "-fx-background-color: #26658C; -fx-text-fill: white; " +
            "-fx-font-size: 14px; -fx-font-weight: bold; " +
            "-fx-background-radius: 8; -fx-padding: 12 28;"
        );
        startButton.setOnMouseEntered(e -> startButton.setStyle(
            "-fx-background-color: #023859; -fx-text-fill: white; " +
            "-fx-font-size: 14px; -fx-font-weight: bold; " +
            "-fx-background-radius: 8; -fx-padding: 12 28;"
        ));
        startButton.setOnMouseExited(e -> startButton.setStyle(
            "-fx-background-color: #26658C; -fx-text-fill: white; " +
            "-fx-font-size: 14px; -fx-font-weight: bold; " +
            "-fx-background-radius: 8; -fx-padding: 12 28;"
        ));

    startButton.setOnAction(e -> {
        closePopup(popupStage);
        // Same entry point a click on an "Available" row in the Quizzes list
        // uses (QuizzesController.startQuiz) - joins the quiz and swaps the
        // main stage's scene to the full quiz-taking screen, replacing the
        // old fixed 620x520 popup this used to open directly.
        QuizTakeController.open(mainStage, dbManager, syncService, quiz.quizId);
    });

        Label footnote = new Label("This quiz will auto-submit when time runs out");
        footnote.setFont(Font.font(10));
        footnote.setTextFill(Color.web("#6B8094"));

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

        // Close precisely when the quiz's own time window ends, rather than
        // waiting for the next 15s poll to merely notice via active-now. The
        // poll keeps running regardless, as a fallback (e.g. if a lecturer
        // edits the quiz's timing after this popup already opened).
        if (popupCloseTimer != null) {
            popupCloseTimer.stop();
        }
        long remainingMs = Math.max(quiz.expiryEpochMillis - System.currentTimeMillis(), 0);
        popupCloseTimer = new javafx.animation.PauseTransition(javafx.util.Duration.millis(remainingMs));
        popupCloseTimer.setOnFinished(e -> closeExpiredPopup());
        popupCloseTimer.play();
    }

    private static void closePopup(Stage popupStage) {
        if (popupCloseTimer != null) {
            popupCloseTimer.stop();
            popupCloseTimer = null;
        }
        if (mainStage != null && mainStage.getScene() != null) {
            mainStage.getScene().getRoot().setEffect(null);
        }
        popupStage.close();
        popupShown = false; // allow future quizzes to trigger again
        currentPopupStage = null;
    }

    /** Called from the poll loop once the shown quiz's time window has
     *  lapsed server-side - same cleanup as closePopup(), just without the
     *  "Start Quiz Now" navigation since there's no quiz left to join. */
    private static void closeExpiredPopup() {
        if (currentPopupStage != null) {
            closePopup(currentPopupStage);
        }
    }
}