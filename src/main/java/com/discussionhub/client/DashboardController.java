package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.ListView;
import javafx.scene.layout.HBox;
import javafx.stage.Stage;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;

public class DashboardController {

    @FXML private Label syncStatusLabel;
    @FXML private Label connectionLabel;
    @FXML private Label lastSyncLabel;
    @FXML private Label pendingCountLabel;
    @FXML private Label recentSyncLabel;
    @FXML private HBox offlineBanner;
    @FXML private ListView<String> syncLogList;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    // Called by HelloApplication after loading this controller
    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        refreshStatus();
    }

    // Refreshes all dashboard labels to reflect current state
    private void refreshStatus() {
        boolean isOnline = NetworkUtil.isNetworkAvailable();

        if (isOnline) {
            syncStatusLabel.setText("● ONLINE");
            syncStatusLabel.setStyle("-fx-text-fill: #90ee90; -fx-font-size: 12; -fx-font-weight: bold;");
            connectionLabel.setText("Connected");
            connectionLabel.setStyle("-fx-font-size: 15; -fx-font-weight: bold; -fx-text-fill: #2e7d32;");
            offlineBanner.setVisible(false);
            offlineBanner.setManaged(false);
        } else {
            syncStatusLabel.setText("● OFFLINE");
            syncStatusLabel.setStyle("-fx-text-fill: #ffcc00; -fx-font-size: 12; -fx-font-weight: bold;");
            connectionLabel.setText("No connection");
            connectionLabel.setStyle("-fx-font-size: 15; -fx-font-weight: bold; -fx-text-fill: #c62828;");
            offlineBanner.setVisible(true);
            offlineBanner.setManaged(true);
        }

        // Show the timestamp of the last successful sync
        String lastSync = dbManager.getLastSyncTimestamp();
        lastSyncLabel.setText("Last sync: " + (lastSync != null ? lastSync : "never"));

        // Show how many items are queued for upload
        int pendingCount = dbManager.getPendingChanges().size();
        pendingCountLabel.setText(pendingCount + (pendingCount == 1 ? " item" : " items"));

        recentSyncLabel.setText(isOnline ? "Sync available" : "Sync paused — offline");
    }

    @FXML
    protected void onSyncNow() {
        if (!NetworkUtil.isNetworkAvailable()) {
            addLogEntry("Sync skipped — no connection.");
            return;
        }
        addLogEntry("Syncing...");
        dbManager.updateDeviceSyncStatus("Syncing");

        // Run sync on a background thread so the UI doesn't freeze
        new Thread(() -> {
            syncService.synchronizeLocalChanges();
            Platform.runLater(() -> {
                refreshStatus();
                addLogEntry("Sync completed at " + nowString());
            });
        }).start();
    }

    @FXML
    protected void onOpenForum() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("forum-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            ForumController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Forum");
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening forum: " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenNotifications() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("notifications-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            NotificationsController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Notifications");
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening notifications: " + e.getMessage());
            e.printStackTrace();
        }
    }

    @FXML
    protected void onTestQuiz() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("quiz-modal.fxml"));
            Scene scene = new Scene(loader.load(), 620, 520);
            QuizModalController controller = loader.getController();

            java.util.List<String> questions = java.util.List.of(
                "What does SQL stand for?",
                "Which Java keyword creates a new object?",
                "What does HTTP stand for?"
            );
            java.util.List<String[]> options = java.util.List.of(
                new String[]{"Structured Query Language","Simple Query Logic","Standard Query List","Structured Question Language"},
                new String[]{"create","new","build","make"},
                new String[]{"HyperText Transfer Protocol","High Transfer Text Protocol","Hyperlink Text Protocol","HyperText Transport Process"}
            );
            controller.setQuizData("test-001", "Sample Quiz - Week 2 Topics", questions, options, 2);

            Stage modalStage = new Stage();
            modalStage.initModality(javafx.stage.Modality.APPLICATION_MODAL);
            modalStage.initStyle(javafx.stage.StageStyle.UNDECORATED);
            modalStage.initOwner(syncStatusLabel.getScene().getWindow());
            modalStage.setScene(scene);
            modalStage.show();
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening quiz: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void addLogEntry(String message) {
        syncLogList.getItems().add(0, nowString() + "  " + message);
    }

    private String nowString() {
        return LocalDateTime.now().format(DateTimeFormatter.ofPattern("HH:mm:ss"));
    }
}