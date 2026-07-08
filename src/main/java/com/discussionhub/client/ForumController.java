package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.model.TopicItem;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.stage.Modality;
import javafx.stage.Stage;

import java.util.List;
import java.util.stream.Collectors;

public class ForumController {

    @FXML private Label syncStatusLabel;
    @FXML private TextField searchField;
    @FXML private ComboBox<String> statusFilter;
    @FXML private ListView<TopicItem> topicListView;
    @FXML private HBox offlineBanner;
    @FXML private Label pageLabel;
    @FXML private Button prevButton;
    @FXML private Button nextButton;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private List<TopicItem> allTopics;

    private int currentPage = 1;
    private static final int PAGE_SIZE = 15;

    // 0 = "no specific group" (old behaviour — shows every locally cached topic).
    // Set to a real GroupID via setGroupContext() when opened from a specific
    // group card on the Dashboard.
    private int currentGroupId = 0;
    private String currentGroupName = "";

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        setupTopicCells();
        setupStatusFilter();
        updateSyncStatusLabel();
        loadTopics();
    }

    /**
     * Call this right after setServices() when opening the Forum for one
     * specific group (e.g. from clicking a group card on the Dashboard).
     * Re-runs loadTopics() so the list is filtered immediately.
     */
    public void setGroupContext(int groupId, String groupName) {
        this.currentGroupId = groupId;
        this.currentGroupName = groupName;
        loadTopics();
        markGroupAsViewed(groupId);
    }

    private void markGroupAsViewed(int groupId) {
        new Thread(() -> {
            try {
                java.net.URL url = java.net.URI.create(
                    "http://127.0.0.1:8000/api/groups/" + groupId + "/mark-viewed").toURL();
                java.net.HttpURLConnection conn = (java.net.HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);
                conn.getOutputStream().write(new byte[0]);
                conn.getResponseCode(); // actually sends the request
            } catch (Exception e) {
                System.err.println("[Forum] Error marking group as viewed: " + e.getMessage());
            }
        }).start();
    }
    /**
     * Custom cell showing all five fields per SDD figure 6.7:
     * title | status badge | author (UserID) | reply count | time
     */
    private void setupTopicCells() {
        topicListView.setCellFactory(listView -> new ListCell<>() {
            @Override
            protected void updateItem(TopicItem item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setGraphic(null); return; }

                HBox row = new HBox(12);
                row.setStyle("-fx-padding: 10; -fx-alignment: CENTER_LEFT;");
                row.setPrefWidth(Double.MAX_VALUE);

                // Title
                Label titleLabel = new Label(item.getTitle());
                titleLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 13;");
                HBox.setHgrow(titleLabel, Priority.ALWAYS);

                // Status badge — defaults to "Open" for new local topics;
                // will be updated to "Answered"/"Pinned" once synced from server
                Label statusBadge = new Label("Open");
                statusBadge.setStyle("-fx-background-color: #e3f2fd; -fx-text-fill: #1565c0;" +
                    "-fx-padding: 2 8; -fx-background-radius: 10; -fx-font-size: 11;");

                // Author — shows the UserID as a number for now;
                // replace with a name lookup once a User table is cached locally
                Label authorLabel = new Label("User #" + item.getCreatedBy());
                authorLabel.setStyle("-fx-text-fill: #555; -fx-font-size: 11;");

                // Real reply count from the LEFT JOIN query in DatabaseManager
                Label replyLabel = new Label(item.getReplyCount() + " "
                    + (item.getReplyCount() == 1 ? "reply" : "replies"));
                replyLabel.setStyle("-fx-text-fill: #888; -fx-font-size: 11;");

                Label timeLabel = new Label(item.getCreatedAt());
                timeLabel.setStyle("-fx-text-fill: #aaa; -fx-font-size: 10.5;");

                row.getChildren().addAll(titleLabel, statusBadge, authorLabel, replyLabel, timeLabel);
                setGraphic(row);
            }
        });
    }

    private void setupStatusFilter() {
        statusFilter.getItems().setAll("All", "My Posts");
        statusFilter.getSelectionModel().selectFirst();
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

    private void loadTopics() {
        List<TopicItem> fetched = dbManager.getAllTopicsWithDetails();

        if (currentGroupId != 0) {
            allTopics = fetched.stream()
                .filter(t -> t.getGroupId() == currentGroupId)
                .collect(Collectors.toList());
        } else {
            allTopics = fetched;
        }

        applyFilterAndSearch();
    }

    private void applyFilterAndSearch() {
        String search = searchField.getText().toLowerCase().trim();
        String selectedFilter = statusFilter.getSelectionModel().getSelectedItem();

        List<TopicItem> filtered = allTopics.stream()
            .filter(t -> search.isEmpty() || t.getTitle().toLowerCase().contains(search))
            // "My Posts" filter — show only topics created by the current user (UserID 1 for now)
            .filter(t -> selectedFilter == null || selectedFilter.equals("All") ||
                (selectedFilter.equals("My Posts") && t.getCreatedBy() == 1) ||
                (!selectedFilter.equals("My Posts")))
            .collect(Collectors.toList());

        int totalPages = Math.max(1, (int) Math.ceil((double) filtered.size() / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        int from = (currentPage - 1) * PAGE_SIZE;
        int to = Math.min(from + PAGE_SIZE, filtered.size());

        topicListView.getItems().clear();
        topicListView.getItems().addAll(filtered.subList(from, to));

        pageLabel.setText("Page " + currentPage + " of " + totalPages);
        prevButton.setDisable(currentPage <= 1);
        nextButton.setDisable(currentPage >= totalPages);
    }

    @FXML protected void onSearch() { currentPage = 1; applyFilterAndSearch(); }
    @FXML protected void onFilterChange() { currentPage = 1; applyFilterAndSearch(); }
    @FXML protected void onPrevPage() { if (currentPage > 1) { currentPage--; applyFilterAndSearch(); } }
    @FXML protected void onNextPage() { currentPage++; applyFilterAndSearch(); }

    @FXML
    protected void onTopicSelected() {
        TopicItem selected = topicListView.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("topic-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            TopicController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            // Pass both title AND the real TopicID so TopicController can load posts
            controller.loadTopic(selected.getTitle(), selected.getTopicId());

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — " + selected.getTitle());
        } catch (Exception e) {
            System.err.println("[Forum] Error opening topic: " + e.getMessage());
        }
    }

    @FXML
    protected void onNewTopic() {
        Dialog<String[]> dialog = new Dialog<>();
        dialog.setTitle("New Topic");
        dialog.setHeaderText("Create a new discussion topic");
        dialog.initModality(Modality.APPLICATION_MODAL);

        ButtonType createButton = new ButtonType("Create", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(createButton, ButtonType.CANCEL);

        TextField titleField = new TextField();
        titleField.setPromptText("Topic title");
        TextField categoryField = new TextField();
        categoryField.setPromptText("Category (e.g. Academic)");

        javafx.scene.layout.VBox content = new javafx.scene.layout.VBox(10,
            new Label("Title:"), titleField,
            new Label("Category:"), categoryField);
        content.setPadding(new javafx.geometry.Insets(16));
        dialog.getDialogPane().setContent(content);

        dialog.setResultConverter(btn ->
            btn == createButton ? new String[]{titleField.getText(), categoryField.getText()} : null);

        dialog.showAndWait().ifPresent(result -> {
            String title = result[0].trim();
            String category = result[1].trim();
            if (title.isEmpty()) return;

            boolean isOnline = NetworkUtil.isNetworkAvailable();
            // TODO: replace 1 with real logged-in UserID once auth exists
            dbManager.handleTopicSubmission(title, category, 1, currentGroupId, isOnline);
            loadTopics();
        });
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Desktop Client");
        } catch (Exception e) {
            System.err.println("[Forum] Error going back: " + e.getMessage());
        }
    }
}
