package com.discussionhub.client.quiz;

import javafx.application.Platform;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Parent;
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
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.util.Timer;
import java.util.TimerTask;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

import com.discussionhub.client.QuizModalController;
import org.json.JSONArray;
import org.json.JSONObject;
import java.util.List;
import java.util.ArrayList;

/**
 * Polls the Laravel quiz-engine endpoint for an active quiz and, when one is
 * found, blurs the main JavaFX window and shows a popup forcing the student
 * to start the quiz.
 *
 * Preferred usage (call once after login, with the session's real auth token):
 *
 *     QuizPopupService.start(stage, SessionManager.token);
 *
 * Fallback usage (uses its own hardcoded test login - avoid in production):
 *
 *     QuizPopupService.start(stage);
 *
 * To stop polling (e.g. on logout or app close):
 *
 *     QuizPopupService.stop();
 */
public class QuizPopupService {

    // TODO: change to your real server address once deployed
    private static final String BASE_URL = "http://127.0.0.1:8000";
    private static final String LOGIN_URL = BASE_URL + "/api/login";
    private static final String ACTIVE_QUIZ_URL = BASE_URL + "/api/quiz/active-now";

    // Fallback credentials, only used if start(Stage) is called without a session token.
    // Prefer start(Stage, String token) wherever a logged-in session is available.
    private static final String STUDENT_EMAIL = "lecturer@test.com";
    private static final String STUDENT_PASSWORD = "password123";

    private static String authToken = null;
    private static boolean usingExternalToken = false;

    private static final int POLL_INTERVAL_MS = 15000; // 15 seconds
    private static final int CONNECT_TIMEOUT_MS = 4000;
    private static final int READ_TIMEOUT_MS = 4000;

    private static Timer pollTimer;
    private static boolean popupShown = false;
    private static Stage mainStage;
    private static Integer lastShownQuizId = null;

    private QuizPopupService() {
        // static utility class
    }

    /**
     * Begin polling for an active quiz using an already-authenticated session token
     * (e.g. SessionManager.token after login). Preferred entry point.
     */
    public static void start(Stage stage, String token) {
        authToken = token;
        usingExternalToken = true;
        startInternal(stage);
    }

    /**
     * Begin polling using the service's own hardcoded test login.
     * Kept for backwards compatibility / standalone testing only.
     */
    public static void start(Stage stage) {
        usingExternalToken = false;
        authToken = null;
        startInternal(stage);
    }

    private static void startInternal(Stage stage) {
        mainStage = stage;
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
        usingExternalToken = false;
    }

    // ---- Polling -----------------------------------------------------
    private static void checkForActiveQuiz() {
        if (popupShown) return; // already showing, skip this poll

        try {
            if (authToken == null) {
                if (usingExternalToken) {
                    // Session token was rejected/expired and we have no way to refresh
                    // it ourselves - stop polling until the app restarts / re-logs in.
                    System.out.println("QuizPopupService: session token missing, stopping poll");
                    stop();
                    return;
                }
                authToken = login();
                if (authToken == null) {
                    System.out.println("QuizPopupService: login failed, skipping this poll");
                    return;
                }
            }

            String json = fetchActiveQuizJson();
            QuizInfo quiz = parseQuiz(json);

            boolean isNewQuiz = quiz != null
                    && (lastShownQuizId == null || lastShownQuizId != quiz.quizId);

            if (isNewQuiz) {
                popupShown = true;
                lastShownQuizId = quiz.quizId;
                Platform.runLater(() -> showQuizPopup(quiz));
            }
        } catch (UnauthorizedException e) {
            System.out.println("QuizPopupService: token rejected");
            authToken = null; // next poll will either stop() (external) or re-login (internal)
        } catch (Exception e) {
            // Network errors are expected when offline; fail silently and retry next poll
            System.out.println("QuizPopupService: poll failed - " + e.getMessage());
        }
    }

    /** Simple marker exception so we can distinguish a 401 from other failures. */
    private static class UnauthorizedException extends Exception {
        UnauthorizedException(String msg) { super(msg); }
    }

    /** Logs in and returns the Sanctum token, or null on failure. */
    private static String login() throws Exception {
        URL url = URI.create(LOGIN_URL).toURL();
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setConnectTimeout(CONNECT_TIMEOUT_MS);
        conn.setReadTimeout(READ_TIMEOUT_MS);
        conn.setRequestProperty("Accept", "application/json");
        conn.setRequestProperty("Content-Type", "application/json");
        conn.setDoOutput(true);

        String body = "{\"email\":\"" + STUDENT_EMAIL + "\",\"password\":\"" + STUDENT_PASSWORD + "\"}";
        try (OutputStream os = conn.getOutputStream()) {
            os.write(body.getBytes("UTF-8"));
        }

        int status = conn.getResponseCode();
        if (status != 200) {
            System.out.println("QuizPopupService: login failed with status " + status);
            return null;
        }

        StringBuilder sb = new StringBuilder();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line);
            }
        }

        Matcher tokenMatch = Pattern.compile("\"token\":\"([^\"]*)\"").matcher(sb.toString());
        return tokenMatch.find() ? tokenMatch.group(1) : null;
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
    closePopup(popupStage);
    new Thread(() -> {
        try {
            String joinJson = fetchQuizJoinJson(quiz.quizId);
            QuizJoinData data = parseJoinResponse(joinJson);

            Platform.runLater(() -> {
                try {
                    FXMLLoader loader = new FXMLLoader(QuizPopupService.class.getResource("/com/discussionhub/client/quiz-modal.fxml"));
                    Parent root = loader.load();
                    QuizModalController controller = loader.getController();
                    controller.setQuizData(
                        String.valueOf(quiz.quizId),
                        data.title,
                        data.questionTexts,
                        data.optionsList,
                        data.questionIds,
                        data.durationMinutes
                    );
                    Stage quizStage = new Stage();
                    quizStage.setScene(new Scene(root));
                    quizStage.initModality(Modality.APPLICATION_MODAL);
                    quizStage.show();
                } catch (IOException ex) {
                    ex.printStackTrace();
                }
            });
        } catch (Exception ex) {
            System.out.println("QuizPopupService: failed to join quiz - " + ex.getMessage());
        }
    }).start();
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
      // ---- Fetch full quiz content (questions/options) before showing the modal ----

private static String fetchQuizJoinJson(int quizId) throws Exception {
    URL url = URI.create(BASE_URL + "/api/quiz/join").toURL();
    HttpURLConnection conn = (HttpURLConnection) url.openConnection();
    conn.setRequestMethod("POST");
    conn.setConnectTimeout(CONNECT_TIMEOUT_MS);
    conn.setReadTimeout(READ_TIMEOUT_MS);
    conn.setRequestProperty("Accept", "application/json");
    conn.setRequestProperty("Content-Type", "application/json");
    conn.setRequestProperty("Authorization", "Bearer " + authToken);
    conn.setDoOutput(true);

    String body = "{\"QuizID\":" + quizId + "}";
    try (OutputStream os = conn.getOutputStream()) {
        os.write(body.getBytes("UTF-8"));
    }

    int status = conn.getResponseCode();
    if (status != 200) {
        throw new RuntimeException("Join failed with status " + status);
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

private static class QuizJoinData {
    String title;
    List<String> questionTexts = new ArrayList<>();
    List<String[]> optionsList = new ArrayList<>();
    List<Integer> questionIds = new ArrayList<>();
    int durationMinutes;
}

private static QuizJoinData parseJoinResponse(String json) {
    JSONObject obj = new JSONObject(json);
    QuizJoinData data = new QuizJoinData();
    data.title = obj.getString("Title");
   

    int allocatedSeconds = obj.getInt("AllocatedSeconds");
    data.durationMinutes = (int) Math.ceil(allocatedSeconds / 60.0);

    JSONArray questions = obj.getJSONArray("Questions");
    for (int i = 0; i < questions.length(); i++) {
        JSONObject q = questions.getJSONObject(i);
        data.questionIds.add(q.getInt("QuestionID")); 
        data.questionTexts.add(q.getString("QuestionText"));

        String optionsRaw = q.isNull("Options") ? "[]" : q.optString("Options", "[]");
        JSONArray optArray = new JSONArray(optionsRaw);
        String[] opts = new String[optArray.length()];
        for (int j = 0; j < optArray.length(); j++) {
            opts[j] = optArray.getString(j);
        }
        data.optionsList.add(opts);
    }

    return data;
}
}