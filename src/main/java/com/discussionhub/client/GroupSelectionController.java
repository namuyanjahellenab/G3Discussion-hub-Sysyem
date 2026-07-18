package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class GroupSelectionController {

    @FXML private Label userEmailLabel;
    @FXML private TextField searchField;
    @FXML private Label statusLabel;
    @FXML private FlowPane groupCardsContainer;
    @FXML private Button continueButton;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private List<GroupData> allGroups = new ArrayList<>();

    // Tracks the checkbox for each group id currently rendered, so onContinue can read final state
    private final Map<Integer, CheckBox> checkboxesByGroupId = new HashMap<>();

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        this.userEmailLabel.setText(SessionManager.userEmail);
        loadGroups();
    }

    private void loadGroups() {
        new Thread(() -> {
            try {
                URL url = URI.create("http://localhost:8000/api/groups/for-selection").toURL();
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
                    if (json.startsWith("[") && json.endsWith("]")) {
                        json = json.substring(1, json.length() - 1);
                        String[] objects = json.split("\\},\\{");
                        List<GroupData> groups = new ArrayList<>();
                        for (String obj : objects) {
                            String cleaned = obj.replace("{", "").replace("}", "");
                            int id = Integer.parseInt(extractValue(cleaned, "id"));
                            String name = extractValue(cleaned, "name");
                            int memberCount = Integer.parseInt(extractValue(cleaned, "member_count"));
                            boolean isMember = Boolean.parseBoolean(extractValue(cleaned, "is_member"));
                            groups.add(new GroupData(id, name, memberCount, isMember));
                        }
                        allGroups = groups;
                        Platform.runLater(() -> renderGroups(allGroups));
                    }
                }
            } catch (Exception e) {
                Platform.runLater(() -> showStatus("Error loading groups: " + e.getMessage()));
                e.printStackTrace();
            }
        }).start();
    }

    private String extractValue(String json, String key) {
        String pattern = "\"" + key + "\":";
        int start = json.indexOf(pattern);
        if (start == -1) return "0";
        start += pattern.length();
        int end;
        if (json.charAt(start) == '\"') {
            start++;
            end = json.indexOf('\"', start);
        } else {
            end = json.indexOf(',', start);
            if (end == -1) end = json.length();
        }
        return json.substring(start, end).replace("\"", "");
    }

    private void renderGroups(List<GroupData> groups) {
        groupCardsContainer.getChildren().clear();
        checkboxesByGroupId.clear();

        for (GroupData group : groups) {
            groupCardsContainer.getChildren().add(createGroupCard(group));
        }
    }

    /** Matches .group-card / .group-card-icon used post-join in DashboardController/ForumController. */
    private VBox createGroupCard(GroupData group) {
        VBox card = new VBox(10);
        card.getStyleClass().add("group-card");
        card.setAlignment(Pos.CENTER);
        card.setPrefSize(210, 210);
        card.setMinSize(210, 210);
        card.setMaxSize(210, 210);
        card.setStyle("-fx-padding: 20 16;");

        Label icon = new Label("\uD83D\uDC65");
        icon.getStyleClass().add("group-card-icon");
        icon.setStyle("-fx-padding: 10 14; -fx-font-size: 16;");

        Label titleLabel = new Label(group.name);
        titleLabel.getStyleClass().add("heading-text");
        titleLabel.setStyle("-fx-font-size: 15;");
        titleLabel.setWrapText(true);
        titleLabel.setAlignment(Pos.CENTER);
        titleLabel.setTextAlignment(javafx.scene.text.TextAlignment.CENTER);

        Label memberLabel = new Label(group.memberCount + (group.memberCount == 1 ? " member" : " members"));
        memberLabel.getStyleClass().add("muted-text");

        // Checkbox selection row (replaces the old instant-join button)
        CheckBox checkBox = new CheckBox(group.isMember ? "Selected" : "Select");
        checkBox.setSelected(group.isMember);
        checkBox.selectedProperty().addListener((obs, wasSelected, isSelected) ->
                checkBox.setText(isSelected ? "Selected" : "Select"));
        checkboxesByGroupId.put(group.id, checkBox);

        card.getChildren().addAll(icon, titleLabel, memberLabel, checkBox);
        return card;
    }

    /**
     * Called when "Proceed to Dashboard" is clicked.
     * Fires a join request for every checked group that isn't already joined,
     * mirroring the web's batch form submit (@csrf groups[] checkboxes -> groups.select.multiple).
     */
    @FXML
    private void onContinue() {
        List<Integer> toJoin = new ArrayList<>();
        for (GroupData group : allGroups) {
            CheckBox cb = checkboxesByGroupId.get(group.id);
            if (cb != null && cb.isSelected() && !group.isMember) {
                toJoin.add(group.id);
            }
        }

        if (toJoin.isEmpty()) {
            loadDashboard();
            return;
        }

        showStatus("Joining selected groups...");
        new Thread(() -> {
            for (int groupId : toJoin) {
                try {
                    URL url = URI.create("http://localhost:8000/api/groups/" + groupId + "/join").toURL();
                    HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                    conn.setRequestMethod("POST");
                    conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                    conn.setRequestProperty("Accept", "application/json");
                    int code = conn.getResponseCode();
                    if (code != 200 && code != 201) {
                        Platform.runLater(() -> showStatus("Failed to join a group. Server returned " + code));
                    }
                } catch (Exception e) {
                    Platform.runLater(() -> showStatus("Error joining group: " + e.getMessage()));
                }
            }
            Platform.runLater(this::loadDashboard);
        }).start();
    }

    @FXML
    private void onSearch() {
        String query = searchField.getText() == null ? "" : searchField.getText().toLowerCase();
        List<GroupData> filtered = new ArrayList<>();
        for (GroupData g : allGroups) {
            if (g.name.toLowerCase().contains(query)) filtered.add(g);
        }
        renderGroups(filtered);
    }

    private void loadDashboard() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) groupCardsContainer.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Dashboard");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void showStatus(String msg) {
        statusLabel.setText(msg);
        statusLabel.setVisible(true);
        statusLabel.setManaged(true);
    }

    private static class GroupData {
        int id;
        String name;
        int memberCount;
        boolean isMember;

        GroupData(int id, String name, int memberCount, boolean isMember) {
            this.id = id;
            this.name = name;
            this.memberCount = memberCount;
            this.isMember = isMember;
        }
    }
}