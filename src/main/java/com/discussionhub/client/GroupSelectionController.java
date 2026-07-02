package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

public class GroupSelectionController {

    @FXML private Label userEmailLabel;
    @FXML private TextField searchField;
    @FXML private Label statusLabel;
    @FXML private FlowPane groupCardsContainer;
    @FXML private Button continueButton;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private List<GroupData> allGroups = new ArrayList<>();

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        this.userEmailLabel.setText(SessionManager.userEmail);
        loadGroups();
    }

    private void loadGroups() {
        new Thread(() -> {
            try {
                URL url = new URL("http://localhost:8000/api/groups");
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

                    // Basic JSON parsing for array of objects
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
        for (GroupData group : groups) {
            VBox card = createGroupCard(group);
            groupCardsContainer.getChildren().add(card);
            if (group.isMember) continueButton.setDisable(false);
        }
    }

    private VBox createGroupCard(GroupData group) {
        VBox card = new VBox(10);
        card.setPrefWidth(220);
        card.setStyle("-fx-background-color: white; -fx-padding: 20; -fx-background-radius: 10; " +
                     "-fx-effect: dropshadow(gaussian,#cccccc,8,0,0,3);");

        Label nameLabel = new Label(group.name);
        nameLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 15; -fx-text-fill: #1a73e8;");
        nameLabel.setWrapText(true);

        Label membersLabel = new Label("👥 " + group.memberCount + " members");
        membersLabel.setStyle("-fx-text-fill: grey; -fx-font-size: 12;");

        Button joinBtn = new Button(group.isMember ? "✓ Joined" : "JOIN");
        joinBtn.setMaxWidth(Double.MAX_VALUE);
        if (group.isMember) {
            joinBtn.setStyle("-fx-background-color: grey; -fx-text-fill: white; -fx-background-radius: 6;");
            joinBtn.setDisable(true);
        } else {
            joinBtn.setStyle("-fx-background-color: #1a73e8; -fx-text-fill: white; -fx-font-weight: bold; -fx-background-radius: 6;");
            joinBtn.setOnAction(e -> onJoinGroup(group.id, joinBtn));
        }

        card.getChildren().addAll(nameLabel, membersLabel, joinBtn);
        return card;
    }

    private void onJoinGroup(int groupId, Button joinBtn) {
        new Thread(() -> {
            try {
                URL url = new URL("http://localhost:8000/api/groups/" + groupId + "/join");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");

                if (conn.getResponseCode() == 200 || conn.getResponseCode() == 201) {
                    Platform.runLater(() -> {
                        joinBtn.setText("✓ Joined");
                        joinBtn.setStyle("-fx-background-color: grey; -fx-text-fill: white; -fx-background-radius: 6;");
                        joinBtn.setDisable(true);
                        continueButton.setDisable(false);
                        showStatus("Group joined successfully!");
                    });
                } else {
                    final int code = conn.getResponseCode();
                    Platform.runLater(() -> showStatus("Failed to join group. Server returned " + code));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showStatus("Error joining group: " + e.getMessage()));
            }
        }).start();
    }

    @FXML
    private void onSearch() {
        String query = searchField.getText().toLowerCase();
        List<GroupData> filtered = new ArrayList<>();
        for (GroupData g : allGroups) {
            if (g.name.toLowerCase().contains(query)) filtered.add(g);
        }
        renderGroups(filtered);
    }

    @FXML
    private void onSkip() {
        loadDashboard();
    }

    @FXML
    private void onContinue() {
        loadDashboard();
    }

    private void loadDashboard() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load(), 900, 650);
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) groupCardsContainer.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Dashboard");
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
