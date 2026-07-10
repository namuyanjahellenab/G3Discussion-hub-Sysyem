package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.database.NotificationItem;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NavigationUtil;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ListView;
import javafx.scene.layout.FlowPane;
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
import javafx.scene.control.Alert;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;

public class DashboardController {
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private FlowPane groupsFlowPane;
    @FXML private VBox recentNotificationsBox;
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
    private GroupSummary firstJoinedGroup; // used by the sidebar's generic "Forum" link

    @FXML
    public void initialize() {
        // Group cards and notifications are built in Java (createGroupCard(),
        // createNotificationRow()) once real data arrives from loadMyGroups()/
        // loadRecentNotifications() — nothing to wire up here at load time.
    }

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        refreshStatus();
        loadUserProfile();
        loadMyGroups();
        loadRecentNotifications();
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
                                boolean hasNew = Boolean.parseBoolean(extractGroupValue(cleaned, "has_new"));
                                myGroups.add(new GroupSummary(groupId, name, memberCount, hasNew));
                            }
                        }
                    }
                }
            } catch (Exception e) {
                System.err.println("[Dashboard] Error loading groups: " + e.getMessage());
            }
            Platform.runLater(() -> {
                groupsFlowPane.getChildren().clear();
                firstJoinedGroup = myGroups.isEmpty() ? null : myGroups.get(0);
                for (GroupSummary group : myGroups) {
                    groupsFlowPane.getChildren().add(createGroupCard(group));
                }
            });
        }).start();
    }

    /**
     * Builds one group card matching the web dashboard's style: centered icon,
     * bold group name, member count, optional "new messages" pill, and a
     * blue "VIEW FORUM →" button that does the actual navigation.
     */
    private VBox createGroupCard(GroupSummary group) {
        VBox card = new VBox(10);
        card.setAlignment(Pos.CENTER);
        card.setPrefWidth(210);
        card.setStyle("-fx-background-color: #fbfbfd; -fx-background-radius: 10; -fx-padding: 20 16; " +
            "-fx-border-color: #eceef3; -fx-border-radius: 10;");

        Label icon = new Label("👥");
        icon.setStyle("-fx-background-color: #eaf1ff; -fx-text-fill: #2f5bea; " +
            "-fx-padding: 10 14; -fx-background-radius: 10; -fx-font-size: 16;");

        Label nameLabel = new Label(group.name);
        nameLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 15; -fx-text-fill: #1a1f36;");

        Label memberLabel = new Label(group.memberCount + (group.memberCount == 1 ? " member" : " members"));
        memberLabel.setStyle("-fx-text-fill: #8a90a0; -fx-font-size: 12;");

        card.getChildren().addAll(icon, nameLabel, memberLabel);

        if (group.hasNew) {
            Label newPill = new Label("● new messages");
            newPill.setStyle("-fx-background-color: #ffe6e6; -fx-text-fill: #d32f2f; " +
                "-fx-padding: 3 8; -fx-background-radius: 10; -fx-font-size: 10.5; -fx-font-weight: bold;");
            card.getChildren().add(newPill);
        }

        Button viewForumBtn = new Button("VIEW FORUM  →");
        viewForumBtn.setMaxWidth(Double.MAX_VALUE);
        viewForumBtn.setStyle("-fx-background-color: #2f5bea; -fx-text-fill: white; -fx-font-weight: bold; " +
            "-fx-background-radius: 6; -fx-padding: 9 16; -fx-font-size: 12;");
        viewForumBtn.setOnAction(e -> openForumForGroup(group));
        card.getChildren().add(viewForumBtn);

        return card;
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

    /**
     * Reads the local Notification table (already-built dbManager.getAllNotifications())
     * and shows the 3 most recent as a small list, matching the web dashboard's
     * "Recent Notifications" card. No new backend calls — purely front-end display
     * of data your app already fetches during sync.
     */
    private void loadRecentNotifications() {
        recentNotificationsBox.getChildren().clear();
        List<NotificationItem> notifications = dbManager.getAllNotifications(SessionManager.userId);

        if (notifications.isEmpty()) {
            Label empty = new Label("No notifications yet.");
            empty.setStyle("-fx-text-fill: #9aa0ab; -fx-font-size: 12;");
            recentNotificationsBox.getChildren().add(empty);
            return;
        }

        int shown = 0;
        for (NotificationItem n : notifications) {
            if (shown >= 3) break;
            recentNotificationsBox.getChildren().add(createNotificationRow(n));
            shown++;
        }
    }

    private VBox createNotificationRow(NotificationItem n) {
        VBox row = new VBox(4);
        row.setStyle("-fx-padding: 0 0 10 0; -fx-border-color: transparent transparent #eceef3 transparent; " +
            "-fx-border-width: 0 0 1 0;");

        HBox topRow = new HBox(8);
        topRow.setAlignment(Pos.CENTER_LEFT);

        Label dot = new Label("●");
        dot.setStyle("-fx-text-fill: #2f5bea; -fx-font-size: 11;");

        Label message = new Label(n.getMessage());
        message.setWrapText(true);
        message.setStyle("-fx-font-weight: bold; -fx-font-size: 13; -fx-text-fill: #1a1f36;");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Label time = new Label(n.getCreatedAt());
        time.setStyle("-fx-text-fill: #b0b5c0; -fx-font-size: 11;");

        topRow.getChildren().addAll(dot, message, spacer, time);
        row.getChildren().add(topRow);
        return row;
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
                loadMyGroups();
                loadRecentNotifications();
                addLogEntry("Sync completed at " + nowString());
            });
        }).start();
    }

    @FXML
    protected void onOpenForum() {
        if (firstJoinedGroup == null) {
            Alert alert = new Alert(Alert.AlertType.INFORMATION);
            alert.setTitle("DiscussionHub");
            alert.setHeaderText(null);
            alert.setContentText("Join a group first, then open its forum from the group card below.");
            alert.showAndWait();
            return;
        }
        openForumForGroup(firstJoinedGroup);
    }

    @FXML
    protected void onOpenGroupSelection() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("group-selection-view.fxml"));
            Parent root = loader.load();
            GroupSelectionController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            NavigationUtil.switchScene(stage, root);
            stage.setTitle("DiscussionHub — Select Group");
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening group selection: " + e.getMessage());
        }
    }

    private void openForumForGroup(GroupSummary group) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("forum-view.fxml"));
            Parent root = loader.load();
            ForumController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.setGroupContext(group.id, group.name);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            NavigationUtil.switchScene(stage, root);
            stage.setTitle("DiscussionHub — " + group.name);
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening group forum: " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenNotifications() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("notifications-view.fxml"));
            Parent root = loader.load();
            NotificationsController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            NavigationUtil.switchScene(stage, root);
            stage.setTitle("DiscussionHub — Notifications");
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening notifications: " + e.getMessage());
            e.printStackTrace();
        }
    }

    @FXML
    protected void onOpenSettings() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("settings-view.fxml"));
            Parent root = loader.load();
            SettingsController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            NavigationUtil.switchScene(stage, root);
            stage.setTitle("DiscussionHub — Settings");
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening settings: " + e.getMessage());
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
        final boolean hasNew;
        GroupSummary(int id, String name, int memberCount, boolean hasNew) {
            this.id = id;
            this.name = name;
            this.memberCount = memberCount;
            this.hasNew = hasNew;
        }
    }
}
