package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

// Mirrors forum/index.blade.php ("Topic Discussions" landing page) exactly:
// stat cards, joined-group cards (each opens the group's own topic list via
// GroupTopicsController), Latest Topics, profile mini, static tip banner.
public class ForumController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    @FXML private Label syncStatusLabel;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label statGroupsLabel;
    @FXML private Label statTopicsLabel;
    @FXML private FlowPane groupsFlowPane;
    @FXML private VBox latestTopicsBox;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("forum");
        loadProfile();
        loadForum();
    }

    private void loadProfile() {
        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
    }

    private void loadForum() {
        new Thread(() -> {
            String body = get("/api/forum");
            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    Platform.runLater(() -> render(json));
                    return;
                } catch (Exception e) {
                    System.err.println("[Forum] Parse error: " + e.getMessage());
                }
            }
            // This overview's own stats/group-membership list isn't cached
            // anywhere locally, so there's nothing to reconstruct for most
            // of this screen - but the single most common case (getting
            // back into the group you were just looking at) doesn't need
            // that: GroupTopicsController/TopicController already have a
            // real offline cache once you're inside them, this is just
            // about getting back in the door.
            Platform.runLater(this::renderOfflineFallback);
        }).start();
    }

    private void renderOfflineFallback() {
        statGroupsLabel.setText("—");
        statTopicsLabel.setText("—");
        groupsFlowPane.getChildren().clear();
        latestTopicsBox.getChildren().clear();

        if (SessionManager.lastGroupId != -1) {
            VBox card = new VBox(10);
            card.getStyleClass().add("group-card");
            card.setAlignment(Pos.CENTER);
            card.setPrefWidth(210);
            card.setStyle("-fx-padding: 20 16;");
            Label icon = new Label("👥");
            icon.getStyleClass().add("group-card-icon");
            icon.setStyle("-fx-padding: 10 14; -fx-font-size: 16;");
            Label nameLabel = new Label(SessionManager.lastGroupName);
            nameLabel.getStyleClass().add("heading-text");
            nameLabel.setStyle("-fx-font-size: 15;");
            Label offlineLabel = new Label("Saved topics available offline");
            offlineLabel.getStyleClass().add("muted-text");
            Button openBtn = new Button("View Forum  →");
            openBtn.getStyleClass().add("btn-primary");
            openBtn.setMaxWidth(Double.MAX_VALUE);
            openBtn.setOnAction(e -> openGroupTopics(SessionManager.lastGroupId, SessionManager.lastGroupName));
            card.getChildren().addAll(icon, nameLabel, offlineLabel, openBtn);
            groupsFlowPane.getChildren().add(card);
        }

        Label empty = new Label(SessionManager.lastGroupId != -1
            ? "You're offline — only your most recently viewed group is available until you reconnect."
            : "You're offline, and no group has been viewed yet on this device to show a saved copy of.");
        empty.getStyleClass().add("muted-text");
        empty.setWrapText(true);
        empty.setStyle("-fx-padding: 24 20;");
        latestTopicsBox.getChildren().add(empty);
    }

    private void render(JSONObject json) {
        JSONArray groups = json.getJSONArray("joined_groups");
        statGroupsLabel.setText(String.valueOf(groups.length()));
        statTopicsLabel.setText(String.valueOf(json.getInt("total_topics_count")));

        groupsFlowPane.getChildren().clear();
        for (int i = 0; i < groups.length(); i++) {
            groupsFlowPane.getChildren().add(createGroupCard(groups.getJSONObject(i)));
        }

        JSONArray topics = json.getJSONArray("latest_topics");
        latestTopicsBox.getChildren().clear();
        if (topics.isEmpty()) {
            Label empty = new Label("No topics yet.");
            empty.getStyleClass().add("muted-text");
            empty.setStyle("-fx-padding: 24 20;");
            latestTopicsBox.getChildren().add(empty);
        } else {
            for (int i = 0; i < topics.length(); i++) {
                latestTopicsBox.getChildren().add(createTopicRow(topics.getJSONObject(i)));
            }
        }
    }

    /** Same square-card look as the Dashboard's My Groups panel (icon, name,
     *  member count, "View Forum" button) rather than forum/index.blade.php's
     *  taller description-card - matches DashboardController.createGroupCard(). */
    private VBox createGroupCard(JSONObject group) {
        VBox card = new VBox(10);
        card.getStyleClass().add("group-card");
        card.setAlignment(Pos.CENTER);
        card.setPrefWidth(210);
        card.setStyle("-fx-padding: 20 16;");

        Label icon = new Label("👥");
        icon.getStyleClass().add("group-card-icon");
        icon.setStyle("-fx-padding: 10 14; -fx-font-size: 16;");

        Label nameLabel = new Label(group.getString("name"));
        nameLabel.getStyleClass().add("heading-text");
        nameLabel.setStyle("-fx-font-size: 15;");

        int memberCount = group.optInt("member_count", 0);
        Label memberLabel = new Label(memberCount + (memberCount == 1 ? " member" : " members"));
        memberLabel.getStyleClass().add("muted-text");

        Button openBtn = new Button("View Forum  →");
        openBtn.getStyleClass().add("btn-primary");
        openBtn.setMaxWidth(Double.MAX_VALUE);
        openBtn.setOnAction(e -> openGroupTopics(group.getInt("id"), group.getString("name")));

        card.getChildren().addAll(icon, nameLabel, memberLabel, openBtn);
        return card;
    }

    private VBox createTopicRow(JSONObject topic) {
        VBox row = new VBox(4);
        row.setStyle("-fx-padding: 14 20; -fx-border-color: transparent transparent #E1E9ED transparent; -fx-border-width: 0 0 1 0;");

        HBox top = new HBox();
        top.setAlignment(Pos.CENTER_LEFT);
        Label title = new Label(topic.getString("title"));
        title.getStyleClass().add("heading-text");
        title.setStyle("-fx-font-size: 13.5;");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        Label time = new Label(topic.optString("time", ""));
        time.getStyleClass().add("muted-text");
        top.getChildren().addAll(title, spacer, time);

        Label meta = new Label("Created by " + topic.optString("creator_name", "a member"));
        meta.getStyleClass().add("muted-text");

        row.getChildren().addAll(top, meta);

        if (!topic.isNull("category") && !topic.optString("category", "").isEmpty()) {
            Label category = new Label(topic.getString("category"));
            category.setStyle("-fx-background-color: #A7EBF2; -fx-text-fill: #023859; " +
                "-fx-padding: 2 10; -fx-background-radius: 999; -fx-font-size: 11; -fx-font-weight: bold;");
            row.getChildren().add(category);
        }

        return row;
    }

    private void openGroupTopics(int groupId, String groupName) {
        // Same field Group Chat remembers its last-opened group in -
        // reused here so there's still a way into a group's (now genuinely
        // cached) topic list on a later offline launch, when this screen's
        // own group cards below have nothing live to render.
        SessionManager.lastGroupId = groupId;
        SessionManager.lastGroupName = groupName;
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("group-topics-view.fxml"));
            Scene scene = new Scene(loader.load());
            GroupTopicsController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.setGroupContext(groupId, groupName);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — " + groupName);
        } catch (Exception e) {
            System.err.println("[Forum] Error opening group topics: " + e.getMessage());
        }
    }

    private String get(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            if (conn.getResponseCode() != 200) return null;
            BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
            StringBuilder response = new StringBuilder();
            String line;
            while ((line = in.readLine()) != null) response.append(line);
            in.close();
            return response.toString();
        } catch (Exception e) {
            System.err.println("[Forum] Request error: " + e.getMessage());
            return null;
        }
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Desktop Client");
        } catch (Exception e) {
            System.err.println("[Forum] Error going back: " + e.getMessage());
        }
    }
}
