package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.database.NotificationItem;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
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
            // Assuming Announcements are a specific type or message pattern
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
