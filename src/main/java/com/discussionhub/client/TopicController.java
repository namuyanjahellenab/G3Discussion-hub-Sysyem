package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.Stage;

import java.util.List;

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

    private String currentTopicTitle;
    private int currentTopicId;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
    }

    // Called by ForumController when a topic is selected
    public void loadTopic(String topicTitle, int topicId) {
        this.currentTopicTitle = topicTitle;
        this.currentTopicId = topicId;

        topicTitleLabel.setText(topicTitle);
        updateSyncStatusLabel();

        // Show the original post as the first card
        originalAuthorLabel.setText("Topic Author");
        originalTimeLabel.setText("— synced locally");
        originalContentLabel.setText(topicTitle);

        // Load replies from local SQLite
        loadReplies(topicId);
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

    private void loadReplies(int topicId) {
        // Clear any previously loaded reply cards (keep the original post card)
        threadContainer.getChildren().clear();
        threadContainer.getChildren().add(originalPostCard);

        if (topicId == -1) {
            // Topic ID not yet known (created offline without a server ID) —
            // nothing to load locally yet since posts need a real TopicID FK.
            Label note = new Label("Replies will appear here after the next sync with the server.");
            note.setStyle("-fx-text-fill: #888; -fx-font-size: 12; -fx-padding: 12;");
            threadContainer.getChildren().add(note);
            return;
        }

        List<String> posts = dbManager.getPostsForTopic(topicId);

        if (posts.isEmpty()) {
            Label note = new Label("No replies yet. Be the first to reply below.");
            note.setStyle("-fx-text-fill: #888; -fx-font-size: 12; -fx-padding: 12;");
            threadContainer.getChildren().add(note);
            return;
        }

        for (String content : posts) {
            // Per SDD figure 6.8: replies are indented below the original post
            VBox replyCard = buildReplyCard(content, "Student", "", false);
            threadContainer.getChildren().add(replyCard);
        }
    }

    // Builds one reply card. isAccepted = green border (per SDD figure 6.8 accepted answer highlight)
    private VBox buildReplyCard(String content, String author, String time, boolean isAccepted) {
        VBox card = new VBox(6);
        card.setPadding(new Insets(12));
        card.setMargin(card, new Insets(0, 0, 0, 24)); // left-indent = threading
        card.setStyle("-fx-background-color: white; -fx-background-radius: 6;" +
                (isAccepted
                        ? "-fx-border-color: #2e7d32; -fx-border-radius: 6; -fx-border-width: 2;"
                        : "-fx-border-color: #eee; -fx-border-radius: 6; -fx-border-width: 1;") +
                "-fx-effect: dropshadow(gaussian, #dddddd, 3, 0, 0, 1);");

        HBox meta = new HBox(10);
        Label authorLabel = new Label(author);
        authorLabel.setStyle("-fx-font-weight: bold; -fx-text-fill: #1a73e8;");
        Label timeLabel = new Label(time);
        timeLabel.setStyle("-fx-text-fill: #888; -fx-font-size: 11;");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        // Per SDD figure 6.8: each post has Filter and Share buttons
        Button filterBtn = new Button("Filter");
        filterBtn.setStyle("-fx-padding: 3 8; -fx-background-radius: 4;");
        Button shareBtn = new Button("Share");
        shareBtn.setStyle("-fx-padding: 3 8; -fx-background-radius: 4;");

        meta.getChildren().addAll(authorLabel, timeLabel, spacer, filterBtn, shareBtn);

        Label contentLabel = new Label(content);
        contentLabel.setWrapText(true);
        contentLabel.setStyle("-fx-font-size: 13;");

        if (isAccepted) {
            Label badge = new Label("✔ Accepted Answer");
            badge.setStyle("-fx-text-fill: #2e7d32; -fx-font-weight: bold; -fx-font-size: 11;");
            card.getChildren().addAll(badge, meta, contentLabel);
        } else {
            card.getChildren().addAll(meta, contentLabel);
        }

        return card;
    }

    @FXML
    protected void onPostReply() {
        String content = replyInput.getText().trim();
        if (content.isEmpty()) return;

        boolean isOnline = NetworkUtil.isNetworkAvailable();
        // TODO: replace 1 with the real logged-in UserID once auth exists
        // TODO: replace currentTopicId with the server-assigned ID once sync returns it
        dbManager.handlePostSubmission(currentTopicId, 1, content, isOnline);

        replyInput.clear();

        // Reload replies so the new one appears immediately
        loadReplies(currentTopicId);
    }

    @FXML
    protected void onExportPdf() {
        // PDF export — per SDD figure 6.8.
        // TODO: implement using iText or Apache PDFBox once the dependency is added to pom.xml.
        // For now, show a placeholder alert so the button is visible and wired.
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
}