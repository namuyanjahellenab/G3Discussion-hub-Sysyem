package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.WindowUtil;
import com.discussionhub.client.utils.AppConfig;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ProgressBar;
import javafx.scene.control.TextArea;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.paint.Color;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.stage.StageStyle;
import javafx.util.Duration;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

// Mirrors resources/views/quizzes/quiz-take.blade.php: a standalone,
// distraction-free screen for taking a quiz (no sidebar, same as the web
// page, which doesn't @extend layouts.app either). Replaces the old
// QuizModalController/quiz-modal.fxml, a fixed 620x520 popup that only
// rendered MCQ RadioButtons (Open-ended questions had no answer control at
// all), never showed Marks/score/a submit confirmation, and had no
// question-nav dots.
public class QuizTakeController {

    private static final String BASE_URL = AppConfig.BASE_URL;

    // Web paginates 3 questions per page (see QUESTIONS_PER_PAGE in
    // quiz-take.blade.php's JS) rather than one question per screen.
    private static final int QUESTIONS_PER_PAGE = 3;

    @FXML private VBox rootBox;
    @FXML private Label quizTitleLabel;
    @FXML private Label quizSubtitleLabel;
    @FXML private Label answeredCountLabel;
    @FXML private Label timerLabel;
    @FXML private FlowPane navDotsBox;
    @FXML private VBox pageContainer;
    @FXML private Label pageInfoLabel;
    @FXML private ProgressBar progressBar;
    @FXML private Button prevButton;
    @FXML private Button nextButton;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    private int quizId;
    private final List<QuestionData> questions = new ArrayList<>();
    private final Map<Integer, String> answers = new LinkedHashMap<>(); // QuestionID -> response text
    private final List<Button> navDots = new ArrayList<>();
    private final Map<Integer, Label> answeredTagsByQuestionId = new LinkedHashMap<>();
    private int currentPage = 0;
    private int totalPages = 1;
    private int secondsLeft;
    private double totalMarks;
    private Timeline timer;
    private boolean submitted = false;

    private static class QuestionData {
        int id;
        String text;
        String type; // "MCQ" or "Open"
        List<String> options = new ArrayList<>();
        double marks;
    }

    // ---- Entry point: join the quiz, then load this screen ------------

    public static void open(Stage stage, DatabaseManager dbManager, DeltaSyncService syncService, int quizId) {
        new Thread(() -> {
            try {
                String body = postJoin(quizId);
                JSONObject json = new JSONObject(body);
                if (json.has("error")) {
                    String error = json.getString("error");
                    Platform.runLater(() -> new Alert(Alert.AlertType.WARNING, error).showAndWait());
                    return;
                }
                Platform.runLater(() -> {
                    try {
                        FXMLLoader loader = new FXMLLoader(QuizTakeController.class.getResource("quiz-take-view.fxml"));
                        Parent root = loader.load();
                        QuizTakeController controller = loader.getController();
                        controller.dbManager = dbManager;
                        controller.syncService = syncService;
                        controller.init(json);
                        Scene scene = new Scene(root);
                        WindowUtil.applyScene(stage, scene, "DiscussionHub — " + json.optString("Title", "Quiz"));
                    } catch (Exception e) {
                        e.printStackTrace();
                    }
                });
            } catch (Exception e) {
                Platform.runLater(() -> new Alert(Alert.AlertType.ERROR, "Could not load quiz: " + e.getMessage()).showAndWait());
            }
        }).start();
    }

    private static String postJoin(int quizId) throws Exception {
        URL url = URI.create(BASE_URL + "/api/quiz/join").toURL();
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
        conn.setRequestProperty("Accept", "application/json");
        conn.setRequestProperty("Content-Type", "application/json");
        conn.setDoOutput(true);
        try (OutputStream os = conn.getOutputStream()) {
            os.write(("{\"QuizID\":" + quizId + "}").getBytes(StandardCharsets.UTF_8));
        }
        int code = conn.getResponseCode();
        try (BufferedReader in = new BufferedReader(new InputStreamReader(
                code >= 400 ? conn.getErrorStream() : conn.getInputStream(), StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = in.readLine()) != null) sb.append(line);
            return sb.toString();
        }
    }

    // ---- Init -----------------------------------------------------------

    private void init(JSONObject json) {
        quizId = json.getInt("QuizID");
        secondsLeft = json.optInt("AllocatedSeconds", 0);

        JSONArray qs = json.getJSONArray("Questions");
        totalMarks = 0;
        for (int i = 0; i < qs.length(); i++) {
            JSONObject q = qs.getJSONObject(i);
            QuestionData qd = new QuestionData();
            qd.id = q.getInt("QuestionID");
            qd.text = q.getString("QuestionText");
            qd.type = q.optString("QuestionType", "MCQ");
            qd.marks = q.optDouble("Marks", 0);
            totalMarks += qd.marks;

            JSONArray optArray = q.optJSONArray("Options");
            if (optArray == null && !q.isNull("Options")) {
                try {
                    optArray = new JSONArray(q.optString("Options", "[]"));
                } catch (Exception ignored) {
                    optArray = new JSONArray();
                }
            }
            if (optArray != null) {
                for (int j = 0; j < optArray.length(); j++) qd.options.add(optArray.getString(j));
            }
            questions.add(qd);
        }

        quizTitleLabel.setText(json.optString("Title", "Quiz"));
        quizSubtitleLabel.setText(questions.size() + " Questions  ·  " + (int) Math.ceil(secondsLeft / 60.0) + " min");
        totalPages = (int) Math.ceil(questions.size() / (double) QUESTIONS_PER_PAGE);

        buildNavDots();
        displayPage(0);
        startTimer();
    }

    // ---- Nav dots (one per question, jump to that question's page) ------

    private void buildNavDots() {
        navDotsBox.getChildren().clear();
        navDots.clear();
        for (int i = 0; i < questions.size(); i++) {
            final int idx = i;
            Button dot = new Button(String.valueOf(i + 1));
            dot.setOnAction(e -> displayPage(idx / QUESTIONS_PER_PAGE));
            navDots.add(dot);
            navDotsBox.getChildren().add(dot);
        }
        updateNavDots();
    }

    private void updateNavDots() {
        int start = currentPage * QUESTIONS_PER_PAGE;
        int end = Math.min(start + QUESTIONS_PER_PAGE, questions.size());
        for (int i = 0; i < navDots.size(); i++) {
            QuestionData q = questions.get(i);
            boolean answered = answers.containsKey(q.id) && !answers.get(q.id).isBlank();
            boolean current = i >= start && i < end;
            String bg = current ? "-luna-mid" : (answered ? "-accent-success" : "-surface-card");
            String fg = current || answered ? "white" : "-text-body";
            navDots.get(i).setStyle(
                "-fx-background-color: " + bg + "; -fx-text-fill: " + fg + "; -fx-font-size: 11.5; -fx-font-weight: bold; " +
                "-fx-background-radius: 999; -fx-min-width: 28; -fx-min-height: 28; -fx-max-width: 28; -fx-max-height: 28; " +
                "-fx-border-color: -surface-border; -fx-border-radius: 999; -fx-cursor: hand; -fx-padding: 0;");
        }
    }

    // ---- Page rendering (up to QUESTIONS_PER_PAGE question cards) -----

    private void displayPage(int page) {
        currentPage = page;
        int start = page * QUESTIONS_PER_PAGE;
        int end = Math.min(start + QUESTIONS_PER_PAGE, questions.size());

        pageContainer.getChildren().clear();
        answeredTagsByQuestionId.clear();
        for (int i = start; i < end; i++) {
            pageContainer.getChildren().add(buildQuestionCard(questions.get(i), i));
        }

        pageInfoLabel.setText("Page " + (page + 1) + " of " + totalPages + "   ·   Q" + (start + 1) + "–Q" + end);
        prevButton.setDisable(page == 0);

        boolean isLastPage = page >= totalPages - 1;
        nextButton.setText(isLastPage ? "Submit Quiz →" : "Next →");
        // Outline button on every page except the last, where it becomes
        // the solid primary action - matches .page-btn vs .page-btn.primary
        // on web (Next only turns into a filled "Submit Quiz" button once
        // you're actually on the final page).
        if (isLastPage) {
            nextButton.setStyle("-fx-background-color: -luna-mid; -fx-text-fill: white; -fx-border-color: -luna-mid; -fx-border-width: 1.5; " +
                "-fx-border-radius: 6; -fx-background-radius: 6; -fx-font-weight: bold; -fx-padding: 9 18; -fx-cursor: hand;");
        } else {
            nextButton.setStyle("-fx-background-color: white; -fx-text-fill: -text-body; -fx-border-color: -surface-border; -fx-border-width: 1.5; " +
                "-fx-border-radius: 6; -fx-background-radius: 6; -fx-font-weight: bold; -fx-padding: 9 18; -fx-cursor: hand;");
        }

        updateNavDots();
        updateProgress();
    }

    private VBox buildQuestionCard(QuestionData q, int globalIndex) {
        VBox card = new VBox(4);
        card.getStyleClass().add("panel-card");
        card.setStyle("-fx-padding: 26;");

        HBox header = new HBox(10);
        header.setAlignment(Pos.CENTER_LEFT);
        Label countLabel = new Label("Question " + (globalIndex + 1) + " of " + questions.size());
        countLabel.getStyleClass().add("muted-text");
        countLabel.setStyle("-fx-font-size: 12.5;");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        Label marksBadge = new Label(fmtNumber(q.marks) + (q.marks == 1 ? " Mark" : " Marks"));
        marksBadge.setStyle("-fx-background-color: -luna-lightest; -fx-text-fill: -luna-dark; -fx-font-size: 11; -fx-font-weight: bold; -fx-padding: 4 12; -fx-background-radius: 999;");
        header.getChildren().addAll(countLabel, spacer, marksBadge);

        Label questionText = new Label(q.text);
        questionText.setWrapText(true);
        questionText.getStyleClass().add("heading-text");
        questionText.setStyle("-fx-font-size: 16; -fx-padding: 12 0 16 0;");

        VBox answerArea = new VBox(10);

        Label answeredTag = new Label("✓ Answer saved");
        answeredTag.setStyle("-fx-text-fill: -accent-success; -fx-font-size: 11.5; -fx-font-weight: bold; -fx-padding: 12 0 0 0;");
        boolean initiallyAnswered = answers.containsKey(q.id) && !answers.get(q.id).isBlank();
        answeredTag.setVisible(initiallyAnswered);
        answeredTag.setManaged(initiallyAnswered);
        answeredTagsByQuestionId.put(q.id, answeredTag);

        String current = answers.get(q.id);
        if ("MCQ".equalsIgnoreCase(q.type) && !q.options.isEmpty()) {
            String[] letters = {"A", "B", "C", "D", "E", "F", "G", "H"};
            List<HBox> rows = new ArrayList<>();
            for (int i = 0; i < q.options.size(); i++) {
                HBox row = optionRow(letters[Math.min(i, letters.length - 1)], q.options.get(i), q.options.get(i).equals(current));
                rows.add(row);
                answerArea.getChildren().add(row);
            }
            for (int i = 0; i < rows.size(); i++) {
                String opt = q.options.get(i);
                HBox row = rows.get(i);
                row.setOnMouseClicked(e -> {
                    if (submitted) return;
                    answers.put(q.id, opt);
                    for (int j = 0; j < rows.size(); j++) {
                        restyleOptionRow(rows.get(j), q.options.get(j).equals(opt));
                    }
                    markAnswered(q.id, true);
                });
            }
        } else {
            TextArea area = new TextArea(current == null ? "" : current);
            area.setWrapText(true);
            area.setPrefRowCount(5);
            area.setPromptText("Type your answer here...");
            area.textProperty().addListener((obs, oldV, newV) -> {
                if (submitted) return;
                boolean has = newV != null && !newV.isBlank();
                if (has) answers.put(q.id, newV.trim()); else answers.remove(q.id);
                markAnswered(q.id, has);
            });
            answerArea.getChildren().add(area);
        }

        card.getChildren().addAll(header, questionText, answerArea, answeredTag);
        return card;
    }

    /** Updates just the answered-tag/progress/dots in place - never rebuilds
     *  a question card, so an in-progress TextArea edit or MCQ selection on
     *  another card in the same page is never disrupted. */
    private void markAnswered(int questionId, boolean answered) {
        Label tag = answeredTagsByQuestionId.get(questionId);
        if (tag != null) {
            tag.setVisible(answered);
            tag.setManaged(answered);
        }
        updateProgress();
        updateNavDots();
    }

    private HBox optionRow(String letter, String text, boolean selected) {
        HBox row = new HBox(12);
        row.setAlignment(Pos.CENTER_LEFT);

        Label letterLabel = new Label(letter);
        letterLabel.setMinSize(26, 26);
        letterLabel.setMaxSize(26, 26);
        letterLabel.setAlignment(Pos.CENTER);

        Label textLabel = new Label(text);
        textLabel.setWrapText(true);
        textLabel.setStyle("-fx-font-size: 13; -fx-text-fill: -text-body;");
        HBox.setHgrow(textLabel, Priority.ALWAYS);

        row.getChildren().addAll(letterLabel, textLabel);
        restyleOptionRow(row, selected);
        return row;
    }

    private void restyleOptionRow(HBox row, boolean selected) {
        row.setStyle("-fx-padding: 12 16; -fx-background-radius: 8; -fx-border-radius: 8; -fx-cursor: hand; " +
            "-fx-background-color: " + (selected ? "-luna-lightest" : "-surface-bg") + "; " +
            "-fx-border-color: " + (selected ? "-luna-mid" : "-surface-border") + "; " +
            "-fx-border-width: " + (selected ? "2" : "1") + ";");
        Label letterLabel = (Label) row.getChildren().get(0);
        letterLabel.setStyle("-fx-background-color: " + (selected ? "-luna-mid" : "-surface-card") + "; " +
            "-fx-text-fill: " + (selected ? "white" : "-text-body") + "; -fx-font-weight: bold; -fx-font-size: 12; " +
            "-fx-background-radius: 999;");
    }

    private String fmtNumber(double m) {
        return (m == Math.floor(m)) ? String.valueOf((int) m) : String.valueOf(m);
    }

    private void updateProgress() {
        int answered = answers.size();
        int total = questions.size();
        answeredCountLabel.setText(answered + " / " + total + " answered");
        progressBar.setProgress(total == 0 ? 0 : (double) answered / total);
    }

    @FXML
    private void onPrevious() {
        if (currentPage > 0) displayPage(currentPage - 1);
    }

    @FXML
    private void onNext() {
        if (currentPage < totalPages - 1) {
            displayPage(currentPage + 1);
        } else {
            showSubmitConfirm();
        }
    }

    // ---- Exit (leave without submitting) -------------------------------

    /** Unlike onPrevious/onNext, leaving mid-quiz abandons whatever isn't
     *  submitted yet - same risk as just closing the app, but a "← Exit"
     *  button invites doing it by accident, so this confirms first (unless
     *  the quiz is already submitted, where there's nothing left to lose). */
    @FXML
    private void onExit() {
        if (submitted) {
            backToDashboard();
            return;
        }
        showExitConfirm();
    }

    private void showExitConfirm() {
        Label heading = new Label("Exit this quiz?");
        heading.getStyleClass().add("heading-text");
        heading.setStyle("-fx-font-size: 17;");

        Label body = new Label("Your answers so far won't be submitted, and the timer keeps running until it expires.");
        body.getStyleClass().add("muted-text");
        body.setWrapText(true);
        body.setStyle("-fx-font-size: 13;");

        Button cancelBtn = new Button("Keep Working");
        cancelBtn.setStyle("-fx-background-color: -surface-bg; -fx-text-fill: -text-body; -fx-padding: 9 20; -fx-background-radius: 8; -fx-cursor: hand;");
        Button exitBtn = new Button("Exit Without Submitting");
        exitBtn.setStyle("-fx-background-color: -accent-danger; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 9 20; -fx-background-radius: 8; -fx-cursor: hand;");

        HBox buttons = new HBox(10, cancelBtn, exitBtn);
        buttons.setAlignment(Pos.CENTER_RIGHT);

        VBox card = new VBox(14, heading, body, buttons);
        card.setStyle("-fx-background-color: -surface-card; -fx-background-radius: 12; -fx-padding: 24; " +
            "-fx-effect: dropshadow(gaussian, rgba(1,28,64,0.25), 20, 0, 0, 6);");
        card.setMaxWidth(380);
        // See the identical fix + longer explanation in showSubmitConfirm()/
        // SidebarController.showNotificationPopup() - a Popup/Stage's content
        // gets its own Scene, and the -surface-card etc. vars are declared on
        // ".root", which only a real Scene root gets auto-attached.
        card.getStyleClass().add("root");
        card.getStylesheets().add(getClass().getResource("app-theme.css").toExternalForm());

        StackPane wrapper = new StackPane(card);
        wrapper.setStyle("-fx-background-color: transparent;");
        wrapper.setPadding(new Insets(20));

        Stage owner = (Stage) rootBox.getScene().getWindow();
        Stage popup = new Stage();
        popup.initOwner(owner);
        popup.initModality(Modality.WINDOW_MODAL);
        popup.initStyle(StageStyle.TRANSPARENT);
        Scene scene = new Scene(wrapper);
        scene.setFill(Color.TRANSPARENT);
        popup.setScene(scene);

        cancelBtn.setOnAction(e -> popup.close());
        exitBtn.setOnAction(e -> {
            popup.close();
            if (timer != null) timer.stop();
            backToDashboard();
        });

        popup.setOnShown(e -> {
            popup.setX(owner.getX() + (owner.getWidth() - popup.getWidth()) / 2);
            popup.setY(owner.getY() + (owner.getHeight() - popup.getHeight()) / 2);
        });
        popup.show();
    }

    // ---- Timer ------------------------------------------------------------

    private void startTimer() {
        updateTimerLabel();
        timer = new Timeline(new KeyFrame(Duration.seconds(1), e -> {
            secondsLeft--;
            updateTimerLabel();
            if (secondsLeft <= 0) {
                timer.stop();
                submitQuiz(true);
            }
        }));
        timer.setCycleCount(Timeline.INDEFINITE);
        timer.play();
    }

    private void updateTimerLabel() {
        int s = Math.max(0, secondsLeft);
        timerLabel.setText(String.format("%02d:%02d", s / 60, s % 60));
        String color = secondsLeft <= 60 ? "-accent-danger" : secondsLeft <= 180 ? "-accent-amber" : "-luna-mid";
        timerLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 22; -fx-text-fill: " + color + ";");
    }

    // ---- Submit confirmation popup -----------------------------------

    private void showSubmitConfirm() {
        int answered = answers.size();
        int unanswered = questions.size() - answered;

        Label heading = new Label("Submit Quiz?");
        heading.getStyleClass().add("heading-text");
        heading.setStyle("-fx-font-size: 17;");

        Label countsLabel = new Label("Answered: " + answered + "   ·   Unanswered: " + unanswered);
        countsLabel.getStyleClass().add("muted-text");
        countsLabel.setStyle("-fx-font-size: 13;");

        Button cancelBtn = new Button("Cancel");
        cancelBtn.setStyle("-fx-background-color: -surface-bg; -fx-text-fill: -text-body; -fx-padding: 9 20; -fx-background-radius: 8; -fx-cursor: hand;");
        Button submitBtn = new Button("Submit Quiz");
        submitBtn.setStyle("-fx-background-color: -luna-mid; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 9 20; -fx-background-radius: 8; -fx-cursor: hand;");

        HBox buttons = new HBox(10, cancelBtn, submitBtn);
        buttons.setAlignment(Pos.CENTER_RIGHT);

        VBox card = new VBox(14, heading, countsLabel, buttons);
        card.setStyle("-fx-background-color: -surface-card; -fx-background-radius: 12; -fx-padding: 24; " +
            "-fx-effect: dropshadow(gaussian, rgba(1,28,64,0.25), 20, 0, 0, 6);");
        card.setMaxWidth(360);
        // A Popup/Stage's content gets its own Scene - the custom vars used
        // above (-surface-card etc.) are declared on ".root" in
        // app-theme.css, and only a real Scene root gets that class
        // auto-attached, so it must be added explicitly here too (see the
        // identical fix + longer explanation in SidebarController.
        // showNotificationPopup()).
        card.getStyleClass().add("root");
        card.getStylesheets().add(getClass().getResource("app-theme.css").toExternalForm());

        StackPane wrapper = new StackPane(card);
        wrapper.setStyle("-fx-background-color: transparent;");
        wrapper.setPadding(new Insets(20));

        Stage owner = (Stage) rootBox.getScene().getWindow();
        Stage popup = new Stage();
        popup.initOwner(owner);
        popup.initModality(Modality.WINDOW_MODAL);
        popup.initStyle(StageStyle.TRANSPARENT);
        Scene scene = new Scene(wrapper);
        scene.setFill(Color.TRANSPARENT);
        popup.setScene(scene);

        cancelBtn.setOnAction(e -> popup.close());
        submitBtn.setOnAction(e -> {
            popup.close();
            submitQuiz(false);
        });

        popup.setOnShown(e -> {
            popup.setX(owner.getX() + (owner.getWidth() - popup.getWidth()) / 2);
            popup.setY(owner.getY() + (owner.getHeight() - popup.getHeight()) / 2);
        });
        popup.show();
    }

    // ---- Submit -------------------------------------------------------

    private void submitQuiz(boolean auto) {
        if (submitted) return;
        submitted = true;
        if (timer != null) timer.stop();

        JSONObject payload = new JSONObject();
        payload.put("QuizID", quizId);
        JSONArray answersArray = new JSONArray();
        for (Map.Entry<Integer, String> e : answers.entrySet()) {
            JSONObject a = new JSONObject();
            a.put("QuestionID", e.getKey());
            a.put("ResponseText", e.getValue());
            answersArray.put(a);
        }
        payload.put("Answers", answersArray);

        String endpoint = auto ? "/api/quiz/auto-submit" : "/api/quiz/submit";

        new Thread(() -> {
            try {
                URL url = URI.create(BASE_URL + endpoint).toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);
                try (OutputStream os = conn.getOutputStream()) {
                    os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
                }

                int code = conn.getResponseCode();
                StringBuilder sb = new StringBuilder();
                try (BufferedReader in = new BufferedReader(new InputStreamReader(
                        code >= 400 ? conn.getErrorStream() : conn.getInputStream(), StandardCharsets.UTF_8))) {
                    String line;
                    while ((line = in.readLine()) != null) sb.append(line);
                }

                if (code == 200 || code == 201) {
                    double score = new JSONObject(sb.toString()).optDouble("Score", 0);
                    Platform.runLater(() -> showResult(score, auto));
                } else {
                    submitted = false;
                    Platform.runLater(() -> new Alert(Alert.AlertType.ERROR, "Could not submit the quiz. Please try again.").showAndWait());
                }
            } catch (Exception e) {
                submitted = false;
                Platform.runLater(() -> new Alert(Alert.AlertType.ERROR, "Could not submit the quiz: " + e.getMessage()).showAndWait());
            }
        }).start();
    }

    private void showResult(double score, boolean auto) {
        navDotsBox.getChildren().clear();
        prevButton.setVisible(false);
        prevButton.setManaged(false);
        nextButton.setVisible(false);
        nextButton.setManaged(false);
        answeredCountLabel.setText("");
        progressBar.setProgress(1);
        quizSubtitleLabel.setText("Completed");
        pageInfoLabel.setText("");

        Label icon = new Label(auto ? "⏰" : "✅");
        icon.setStyle("-fx-font-size: 42;");

        Label scoreLabel = new Label(fmtNumber(score) + " / " + fmtNumber(totalMarks));
        scoreLabel.setStyle("-fx-font-size: 30; -fx-font-weight: bold; -fx-text-fill: -luna-mid;");

        Label msg = new Label(auto
            ? "Time expired. Your answers were saved automatically."
            : "Your answers have been saved successfully.");
        msg.getStyleClass().add("muted-text");
        msg.setWrapText(true);
        msg.setStyle("-fx-font-size: 13;");

        Button backBtn = new Button("Back to Dashboard");
        backBtn.setStyle("-fx-background-color: -luna-mid; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 24; -fx-background-radius: 8; -fx-cursor: hand;");
        backBtn.setOnAction(e -> backToDashboard());

        VBox resultBox = new VBox(12, icon, scoreLabel, msg, backBtn);
        resultBox.setAlignment(Pos.CENTER);
        resultBox.getStyleClass().add("panel-card");
        resultBox.setStyle("-fx-padding: 30 10;");

        pageContainer.getChildren().setAll(resultBox);
    }

    private void backToDashboard() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            Stage stage = (Stage) rootBox.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Dashboard");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
