package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.Stage;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * Topic thread screen: shows the original post plus every reply and lets
 * the user post a new reply, mirroring topics/show.blade.php exactly
 * (backed by DiscussionHubPageController::showTopic()/storeReply()/
 * acceptAnswer()) via GET/POST /api/topics, /api/posts/{post}/reply and
 * /api/replies/{reply}/accept. This replaces the previous screen, which
 * only read locally-cached SQLite data and showed placeholder author
 * names ("Topic Author" / "Student").
 */
public class TopicController {

    @FXML private Label syncStatusLabel;
    @FXML private Label topicTitleLabel;
    @FXML private VBox originalPostCard;
    @FXML private Label originalAuthorLabel;
    @FXML private Label originalTimeLabel;
    @FXML private Label originalContentLabel;
    @FXML private VBox threadContainer;
    @FXML private HBox offlineBanner;
    @FXML private TextArea replyInput;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    private int currentTopicId;
    private int currentMainPostId = -1;
    private boolean canAccept = false;

    private static final String BASE_URL = "http://localhost:8000/api";

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
    }

    // Called by ForumController when a topic is selected
    public void loadTopic(int topicId) {
        this.currentTopicId = topicId;
        updateSyncStatusLabel();
        fetchTopic();
    }

    private void updateSyncStatusLabel() {
        boolean isOnline = NetworkUtil.isNetworkAvailable();
        if (isOnline) {
            syncStatusLabel.setText("● ONLINE");
            syncStatusLabel.setStyle("-fx-text-fill: #90ee90; -fx-font-size: 12; -fx-font-weight: bold;");
            offlineBanner.setVisible(false);
            offlineBanner.setManaged(false);
        } else {
            syncStatusLabel.setText("● OFFLINE");
            syncStatusLabel.setStyle("-fx-text-fill: #ffcc00; -fx-font-size: 12; -fx-font-weight: bold;");
            offlineBanner.setVisible(true);
            offlineBanner.setManaged(true);
        }
    }

    /**
     * Loads the full thread from GET /api/topics/{id} — same data as
     * DiscussionHubPageController::showTopic() (main post + all replies,
     * with author, accepted-answer and quoted-reply info).
     */
    private void fetchTopic() {
        new Thread(() -> {
            try {
                URL url = URI.create(BASE_URL + "/topics/" + currentTopicId).toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("GET");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");
                int responseCode = conn.getResponseCode();
                if (responseCode == 200) {
                    JSONObject json = new JSONObject(readBody(conn));
                    Platform.runLater(() -> renderTopic(json));
                } else {
                    Platform.runLater(() -> showError("Could not load this topic (server returned " + responseCode + ")."));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showError("Error loading topic: " + e.getMessage()));
            }
        }).start();
    }

    private void renderTopic(JSONObject json) {
        String title = json.optString("title", "");
        topicTitleLabel.setText(title);
        canAccept = json.optBoolean("can_accept", false);

        threadContainer.getChildren().clear();

        JSONObject mainPost = json.optJSONObject("main_post");
        if (mainPost == null) {
            Label note = new Label("No discussion started in this topic yet.");
            note.setStyle("-fx-text-fill: #888; -fx-font-size: 12; -fx-padding: 12;");
            threadContainer.getChildren().add(note);
            replyInput.setDisable(true);
            return;
        }

        currentMainPostId = mainPost.getInt("id");

        originalAuthorLabel.setText(mainPost.optString("author", "a member"));
        originalTimeLabel.setText(mainPost.optString("created_at_human", ""));
        originalContentLabel.setText(mainPost.optString("content", ""));
        threadContainer.getChildren().add(originalPostCard);

        JSONArray replies = mainPost.optJSONArray("replies");
        if (replies == null || replies.isEmpty()) {
            Label note = new Label("No replies yet. Be the first to respond.");
            note.setStyle("-fx-text-fill: #888; -fx-font-size: 12; -fx-padding: 12;");
            threadContainer.getChildren().add(note);
            return;
        }

        for (int i = 0; i < replies.length(); i++) {
            threadContainer.getChildren().add(buildReplyCard(replies.getJSONObject(i)));
        }
    }

    /**
     * Builds one reply card mirroring the .bubble / .bubble-quote /
     * .accepted-tag markup in topics/show.blade.php: green border + badge
     * for accepted answers, a quoted-reply preview box, a "Lecturer" tag,
     * and a lecturer-only "Mark as Accepted" button.
     */
    private VBox buildReplyCard(JSONObject reply) {
        int replyId = reply.getInt("id");
        String content = reply.optString("content", "");
        String author = reply.optString("author", "a member");
        String authorRole = reply.optString("author_role", "");
        boolean isAccepted = reply.optBoolean("is_accepted", false);
        String time = reply.optString("created_at_human", "");
        JSONObject quoted = reply.optJSONObject("quoted");

        VBox card = new VBox(6);
        card.setPadding(new Insets(12));
        VBox.setMargin(card, new Insets(0, 0, 0, 24)); // left-indent = threading
        card.setStyle("-fx-background-color: white; -fx-background-radius: 6;" +
            (isAccepted
                ? "-fx-border-color: #12b76a; -fx-border-radius: 6; -fx-border-width: 2;"
                : "-fx-border-color: #eee; -fx-border-radius: 6; -fx-border-width: 1;") +
            "-fx-effect: dropshadow(gaussian, #dddddd, 3, 0, 0, 1);");

        if (isAccepted) {
            Label badge = new Label("✔ Accepted Answer");
            badge.setStyle("-fx-text-fill: #12b76a; -fx-font-weight: bold; -fx-font-size: 11;");
            card.getChildren().add(badge);
        }

        HBox meta = new HBox(10);
        Label authorLabel = new Label(author + ("Lecturer".equals(authorRole) ? "  ·  Lecturer" : ""));
        authorLabel.setStyle("-fx-font-weight: bold; -fx-text-fill: " +
            ("Lecturer".equals(authorRole) ? "#12b76a" : "#1a73e8") + ";");
        Label timeLabel = new Label(time);
        timeLabel.setStyle("-fx-text-fill: #888; -fx-font-size: 11;");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        meta.getChildren().addAll(authorLabel, timeLabel, spacer);

        if (!isAccepted && canAccept) {
            Button acceptBtn = new Button("Mark as Accepted");
            acceptBtn.setStyle("-fx-padding: 3 8; -fx-background-radius: 4; -fx-font-size: 11;" +
                "-fx-background-color: #ecfdf3; -fx-text-fill: #12b76a; -fx-font-weight: bold;");
            acceptBtn.setOnAction(e -> onAcceptAnswer(replyId));
            meta.getChildren().add(acceptBtn);
        }

        card.getChildren().add(meta);

        if (quoted != null) {
            VBox quoteBox = new VBox(2);
            quoteBox.setStyle("-fx-border-color: transparent transparent transparent #1a73e8;" +
                "-fx-border-width: 0 0 0 3; -fx-background-color: #eef4ff; -fx-padding: 5 9;" +
                "-fx-background-radius: 6;");
            Label quoteText = new Label(quoted.optString("author", "") + ": " + quoted.optString("snippet", ""));
            quoteText.setWrapText(true);
            quoteText.setStyle("-fx-font-size: 11; -fx-text-fill: #344054;");
            quoteBox.getChildren().add(quoteText);
            card.getChildren().add(quoteBox);
        }

        Label contentLabel = new Label(content);
        contentLabel.setWrapText(true);
        contentLabel.setStyle("-fx-font-size: 13;");
        card.getChildren().add(contentLabel);

        return card;
    }

    /**
     * Posts a reply via POST /api/posts/{post}/reply — the exact same
     * logic (including auto-marking the topic "answered" when a lecturer
     * replies) as DiscussionHubPageController::storeReply().
     */
    @FXML
    protected void onPostReply() {
        String content = replyInput.getText() == null ? "" : replyInput.getText().trim();
        if (content.isEmpty() || currentMainPostId == -1) return;

        replyInput.setDisable(true);
        new Thread(() -> {
            try {
                URL url = URI.create(BASE_URL + "/posts/" + currentMainPostId + "/reply").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                JSONObject payload = new JSONObject();
                payload.put("ReplyContent", content);

                try (OutputStream os = conn.getOutputStream()) {
                    os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
                }

                int code = conn.getResponseCode();
                if (code == 201) {
                    Platform.runLater(() -> {
                        replyInput.clear();
                        replyInput.setDisable(false);
                        fetchTopic(); // reload so the new reply appears immediately
                    });
                } else {
                    Platform.runLater(() -> {
                        replyInput.setDisable(false);
                        showError("Could not post reply (server returned " + code + ").");
                    });
                }
            } catch (Exception e) {
                Platform.runLater(() -> {
                    replyInput.setDisable(false);
                    showError("Error posting reply: " + e.getMessage());
                });
            }
        }).start();
    }

    /**
     * Lecturer-only "Mark as Accepted" via POST /api/replies/{reply}/accept
     * — same rule as DiscussionHubPageController::acceptAnswer().
     */
    private void onAcceptAnswer(int replyId) {
        new Thread(() -> {
            try {
                URL url = URI.create(BASE_URL + "/replies/" + replyId + "/accept").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);
                conn.getOutputStream().write(new byte[0]);

                int code = conn.getResponseCode();
                if (code == 200) {
                    Platform.runLater(this::fetchTopic);
                } else {
                    Platform.runLater(() -> showError("Could not accept this reply (server returned " + code + ")."));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showError("Error accepting reply: " + e.getMessage()));
            }
        }).start();
    }

    @FXML
    protected void onExportPdf() {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Export to PDF");
        alert.setHeaderText(null);
        alert.setContentText("PDF export is not yet implemented. It will generate a downloadable PDF of this thread.");
        alert.showAndWait();
    }

    @FXML
    protected void onFilterPost() {
        // Filter/hide post — per SDD (Filtered status badge)
        // TODO: implement filter toggle once post IDs are available
    }

    @FXML
    protected void onSharePost() {
        // Social share modal — per SDD figure 6.12
        // TODO: implement once the share screen is built
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("forum-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            ForumController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Forum");
        } catch (Exception e) {
            System.err.println("[Topic] Error going back: " + e.getMessage());
        }
    }

    private void showError(String message) {
        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle("DiscussionHub");
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private static String readBody(HttpURLConnection conn) throws Exception {
        StringBuilder sb = new StringBuilder();
        try (BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
            String line;
            while ((line = in.readLine()) != null) sb.append(line);
        }
        return sb.toString();
    }
}
