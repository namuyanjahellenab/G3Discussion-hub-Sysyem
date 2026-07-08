package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.ListCell;
import javafx.scene.control.ListView;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;

public class DashboardController {
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private ListView<GroupSummary> groupsListView;
    @FXML private HBox syncCompleteBanner;
    @FXML private Label syncDetailLabel;
    @FXML private VBox pendingUploadsCard;
    @FXML private Label syncStatusLabel;
    @FXML private Label connectionLabel;
    @FXML private Label lastSyncLabel;
    @FXML private Label pendingCountLabel;
    @FXML private Label recentSyncLabel;
    @FXML private HBox offlineBanner;
    @FXML private ListView<String> syncLogList;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    @FXML
    public void initialize() {
        groupsListView.setCellFactory(lv -> new ListCell<>() {
            @Override
            protected void updateItem(GroupSummary item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }
                HBox row = new HBox(12);
                row.setAlignment(Pos.CENTER_LEFT);
                row.setStyle("-fx-padding: 12; -fx-background-color: #f7f8fc; -fx-background-radius: 8;");

                Label icon = new Label("👥");
                icon.setStyle("-fx-background-color: #eaf1ff; -fx-text-fill: #2f5bea; " +
                    "-fx-padding: 8 10; -fx-background-radius: 8; -fx-font-size: 13;");

                VBox textBox = new VBox(2);
                Label nameLabel = new Label(item.name);
                nameLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 13; -fx-text-fill: #1a1f36;");
                Label subLabel = new Label(item.memberCount + (item.memberCount == 1 ? " member" : " members"));
                subLabel.setStyle("-fx-text-fill: #8a90a0; -fx-font-size: 11.5;");
                textBox.getChildren().addAll(nameLabel, subLabel);

                Region spacer = new Region();
                HBox.setHgrow(spacer, Priority.ALWAYS);

                row.getChildren().addAll(icon, textBox, spacer);
                setGraphic(row);
                setText(null);
                setStyle("-fx-background-color: transparent; -fx-padding: 3 0;");
                row.setOnMouseClicked(e -> openForumForGroup(item));
            }
        });
    }

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        refreshStatus();
        loadUserProfile();
        loadMyGroups();
    }

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

        String lastSync = dbManager.getLastSyncTimestamp();
        lastSyncLabel.setText("Last sync: " + (lastSync != null ? lastSync : "never"));

        int pendingCount = dbManager.getPendingChanges().size();
        pendingCountLabel.setText(pendingCount + (pendingCount == 1 ? " item" : " items"));

        recentSyncLabel.setText(isOnline ? "Sync available" : "Sync paused — offline");
    }

    private void loadUserProfile() {
        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(initialsOf(name));
    }

    private String initialsOf(String name) {
        StringBuilder sb = new StringBuilder();
        for (String part : name.trim().split("\\s+")) {
            if (!part.isEmpty()) sb.append(Character.toUpperCase(part.charAt(0)));
            if (sb.length() >= 2) break;
        }
        return sb.length() > 0 ? sb.toString() : "?";
    }

    private void loadMyGroups() {
        new Thread(() -> {
            List<GroupSummary> myGroups = new ArrayList<>();
            try {
                URL url = URI.create("http://localhost:8000/api/groups").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("GET");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");

                if (conn.getResponseCode() == 200) {
                    BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                    StringBuilder response = new StringBuilder();
                    String line;
                    while ((line = in.readLine()) != null) response.append(line);
                    in.close();

                    String json = response.toString().trim();
                    if (json.startsWith("[") && json.endsWith("]") && json.length() > 2) {
                        json = json.substring(1, json.length() - 1);
                        for (String obj : json.split("\\},\\{")) {
                            String cleaned = obj.replace("{", "").replace("}", "");
                            boolean isMember = Boolean.parseBoolean(extractGroupValue(cleaned, "is_member"));
                            if (isMember) {
                                int groupId = Integer.parseInt(extractGroupValue(cleaned, "id"));
                                String name = extractGroupValue(cleaned, "name");
                                int memberCount = Integer.parseInt(extractGroupValue(cleaned, "member_count"));
                                myGroups.add(new GroupSummary(groupId, name, memberCount));
                            }
                        }
                    }
                }
            } catch (Exception e) {
                System.err.println("[Dashboard] Error loading groups: " + e.getMessage());
            }
            Platform.runLater(() -> groupsListView.getItems().setAll(myGroups));
        }).start();
    }

    private String extractGroupValue(String json, String key) {
        String pattern = "\"" + key + "\":";
        int start = json.indexOf(pattern);
        if (start == -1) return "0";
        start += pattern.length();
        int end;
        if (json.charAt(start) == '"') {
            start++;
            end = json.indexOf('"', start);
        } else {
            end = json.indexOf(',', start);
            if (end == -1) end = json.length();
        }
        return json.substring(start, end).replace("\"", "");
    }

    @FXML
    protected void onSyncNow() {
        if (!NetworkUtil.isNetworkAvailable()) {
            addLogEntry("Sync skipped — no connection.");
            return;
        }
        addLogEntry("Syncing...");
        dbManager.updateDeviceSyncStatus("Syncing");

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

    private void openForumForGroup(GroupSummary group) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("forum-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            ForumController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.setGroupContext(group.id, group.name);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — " + group.name);
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening group forum: " + e.getMessage());
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
            java.util.List<Integer> questionIds = java.util.List.of(-1, -2, -3);
            controller.setQuizData("-1", "Sample Quiz - Week 2 Topics (PREVIEW)", questions, options, questionIds, 2);

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

    private static class GroupSummary {
        final int id;
        final String name;
        final int memberCount;
        GroupSummary(int id, String name, int memberCount) {
            this.id = id;
            this.name = name;
            this.memberCount = memberCount;
        }
    }
}
