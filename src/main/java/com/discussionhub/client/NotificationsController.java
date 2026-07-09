package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.database.NotificationItem;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.ToggleButton;
import javafx.scene.control.ToggleGroup;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.stage.Stage;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;

public class NotificationsController {

    @FXML private VBox notificationsContainer;
    @FXML private Label emptyStateLabel;
    @FXML private Label syncStatusLabel;
    @FXML private ToggleButton filterAll, filterUnread, filterAnnouncements;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private ToggleGroup filterGroup;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;

        filterGroup = new ToggleGroup();
        filterAll.setToggleGroup(filterGroup);
        filterUnread.setToggleGroup(filterGroup);
        filterAnnouncements.setToggleGroup(filterGroup);

        updateSyncStatus();
        loadNotifications("All");
    }

    private void updateSyncStatus() {
        boolean isOnline = NetworkUtil.isNetworkAvailable();
        syncStatusLabel.setText(isOnline ? "● ONLINE" : "● OFFLINE");
        syncStatusLabel.setStyle(isOnline ? "-fx-text-fill: white;" : "-fx-text-fill: #ffcc00;");
    }

    private void loadNotifications(String filter) {
        notificationsContainer.getChildren().clear();
        List<NotificationItem> all = dbManager.getAllNotifications(SessionManager.userId);

        List<NotificationItem> filtered = all;
        if ("Unread".equals(filter)) {
            filtered = all.stream().filter(n -> n.getStatus() == 0).collect(Collectors.toList());
        } else if ("Announcements".equals(filter)) {
            filtered = all.stream().filter(n -> n.getType().equalsIgnoreCase("Announcement")).collect(Collectors.toList());
        }

        if (filtered.isEmpty()) {
            emptyStateLabel.setVisible(true);
            notificationsContainer.getChildren().add(emptyStateLabel);
        } else {
            emptyStateLabel.setVisible(false);
            for (NotificationItem item : filtered) {
                notificationsContainer.getChildren().add(createNotificationCard(item));
            }
        }

        // Live "new messages" section — only meaningful for All/Unread, since
        // group updates aren't a stored Announcement type.
        if ("All".equals(filter) || "Unread".equals(filter)) {
            loadLiveGroupUpdates();
        }
    }

    /**
     * Hits GET /api/groups directly (same as Dashboard's loadMyGroups()) and,
     * for every group with has_new == true, prepends a "New messages in X"
     * card at the top of the list. This is computed live at request time —
     * nothing here touches the local Notification table.
     */
    private void loadLiveGroupUpdates() {
        new Thread(() -> {
            List<String> groupsWithNewMessages = new ArrayList<>();
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
                            boolean isMember = Boolean.parseBoolean(extractValue(cleaned, "is_member"));
                            boolean hasNew = Boolean.parseBoolean(extractValue(cleaned, "has_new"));
                            if (isMember && hasNew) {
                                groupsWithNewMessages.add(extractValue(cleaned, "name"));
                            }
                        }
                    }
                }
            } catch (Exception e) {
                System.err.println("[Notifications] Error checking live group updates: " + e.getMessage());
            }

            Platform.runLater(() -> {
                if (!groupsWithNewMessages.isEmpty()) {
                    emptyStateLabel.setVisible(false);
                    notificationsContainer.getChildren().remove(emptyStateLabel);
                    int insertIndex = 0;
                    for (String groupName : groupsWithNewMessages) {
                        notificationsContainer.getChildren().add(insertIndex++, createLiveGroupCard(groupName));
                    }
                }
            });
        }).start();
    }

    private VBox createLiveGroupCard(String groupName) {
        VBox card = new VBox(8);
        card.setPadding(new Insets(15));
        card.setStyle("-fx-background-color: #eaf1ff; -fx-background-radius: 8; " +
            "-fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.1), 5, 0, 0, 2);");

        HBox topRow = new HBox(10);
        Label dot = new Label("●");
        dot.setStyle("-fx-text-fill: #2f5bea; -fx-font-size: 16;");

        Label msg = new Label("New messages in " + groupName);
        msg.setWrapText(true);
        msg.setStyle("-fx-font-size: 14; -fx-font-weight: bold;");

        topRow.getChildren().addAll(dot, msg);

        Label typeBadge = new Label("Group Update");
        typeBadge.setPadding(new Insets(2, 8, 2, 8));
        typeBadge.setStyle("-fx-background-radius: 4; -fx-text-fill: white; -fx-font-size: 10; " +
            "-fx-font-weight: bold; -fx-background-color: #2f5bea;");

        card.getChildren().addAll(topRow, typeBadge);
        return card;
    }

    private String extractValue(String json, String key) {
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

    private VBox createNotificationCard(NotificationItem item) {
        VBox card = new VBox(8);
        card.setPadding(new Insets(15));
        card.setStyle("-fx-background-color: white; -fx-background-radius: 8; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.1), 5, 0, 0, 2);");

        if (item.getType().equalsIgnoreCase("Announcement")) {
            card.setStyle(card.getStyle() + "-fx-background-color: #fff4ce;");
        }

        HBox topRow = new HBox(10);
        Label dot = new Label(item.getStatus() == 0 ? "●" : "○");
        dot.setStyle("-fx-text-fill: #1a73e8; -fx-font-size: 16;");

        Label msg = new Label(item.getMessage());
        msg.setWrapText(true);
        msg.setStyle("-fx-font-size: 14;");

        topRow.getChildren().addAll(dot, msg);

        Label time = new Label(item.getCreatedAt());
        time.setStyle("-fx-text-fill: grey; -fx-font-size: 11;");

        Label typeBadge = new Label(item.getType());
        typeBadge.setPadding(new Insets(2, 8, 2, 8));
        typeBadge.setStyle("-fx-background-radius: 4; -fx-text-fill: white; -fx-font-size: 10; -fx-font-weight: bold;");

        if (item.getType().equalsIgnoreCase("Warning")) {
            typeBadge.setStyle(typeBadge.getStyle() + "-fx-background-color: #e53935;");
        } else if (item.getType().equalsIgnoreCase("Quiz")) {
            typeBadge.setStyle(typeBadge.getStyle() + "-fx-background-color: #1a73e8;");
        } else {
            typeBadge.setStyle(typeBadge.getStyle() + "-fx-background-color: grey;");
        }

        card.getChildren().addAll(topRow, time, typeBadge);
        return card;
    }

    @FXML
    public void onFilterAll() {
        applyToggleStyle(filterAll);
        loadNotifications("All");
    }

    @FXML
    public void onFilterUnread() {
        applyToggleStyle(filterUnread);
        loadNotifications("Unread");
    }

    @FXML
    public void onFilterAnnouncements() {
        applyToggleStyle(filterAnnouncements);
        loadNotifications("Announcements");
    }

    private void applyToggleStyle(ToggleButton active) {
        filterAll.setStyle("-fx-background-radius: 4; -fx-padding: 6 15; -fx-background-color: white;");
        filterUnread.setStyle("-fx-background-radius: 4; -fx-padding: 6 15; -fx-background-color: white;");
        filterAnnouncements.setStyle("-fx-background-radius: 4; -fx-padding: 6 15; -fx-background-color: white;");

        active.setStyle("-fx-background-radius: 4; -fx-padding: 6 15; -fx-background-color: #1a73e8; -fx-text-fill: white;");
    }

    @FXML
    public void onMarkAllRead() {
        dbManager.markAllNotificationsAsRead(SessionManager.userId);
        loadNotifications("All");
    }

    @FXML
    public void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) notificationsContainer.getScene().getWindow();
            stage.setScene(scene);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
