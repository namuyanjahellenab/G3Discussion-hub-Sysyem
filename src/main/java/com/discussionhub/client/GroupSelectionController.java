package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.HBox;
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
    @FXML private GridPane groupCardsContainer;
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

    // --- Accent + course-code mapping, mirrors the Blade match() logic exactly ---

    private String accentColorFor(String groupName) {
        return switch (groupName) {
            case "Algorithms" -> "#2f80ed";
            case "Databases" -> "#56ccf2";
            case "Software Engineering", "Software Eng." -> "#4f5e71";
            case "Networks" -> "#b91c1c";
            default -> "#98a2b3";
        };
    }

    private String courseCodeFor(String groupName) {
        return switch (groupName) {
            case "Algorithms" -> "CSC301";
            case "Databases" -> "CSC302";
            case "Software Engineering", "Software Eng." -> "CSC303";
            case "Networks" -> "CSC304";
            default -> "CSC300";
        };
    }

    private void renderGroups(List<GroupData> groups) {
        groupCardsContainer.getChildren().clear();
        checkboxesByGroupId.clear();

        int index = 0;
        for (GroupData group : groups) {
            VBox card = createGroupCard(group);
            int col = index % 2;
            int row = index / 2;
            groupCardsContainer.add(card, col, row);
            index++;
        }
    }

    private VBox createGroupCard(GroupData group) {
        String accent = accentColorFor(group.name);
        String courseCode = courseCodeFor(group.name);

        VBox card = new VBox(14);
        // 4-value border-color/-width give the web's 6px colored left accent with a thin border on the other 3 sides
        card.setStyle(String.format(
                "-fx-background-color: white;" +
                "-fx-background-radius: 16;" +
                "-fx-border-color: #e4e7ec #e4e7ec #e4e7ec %s;" +
                "-fx-border-width: 1 1 1 6;" +
                "-fx-border-radius: 16;" +
                "-fx-padding: 20 24 20 18;" +
                "-fx-effect: dropshadow(gaussian, rgba(16,24,40,0.08), 6, 0, 0, 2);",
                accent
        ));

        // Top meta row: course badge + member pill
        Label courseBadge = new Label(courseCode);
        courseBadge.setStyle(String.format(
                "-fx-text-fill: %s; -fx-background-color: derive(%s, 92%%);" +
                "-fx-font-weight: bold; -fx-font-size: 11; -fx-padding: 4 10; -fx-background-radius: 6;",
                accent, accent));

        Label memberPill = new Label("\uD83D\uDC65 " + group.memberCount + " members");
        memberPill.setStyle(
                "-fx-background-color: #eef4ff; -fx-text-fill: #0d52cc;" +
                "-fx-font-size: 11; -fx-padding: 5 14; -fx-background-radius: 20;");

        HBox metaRow = new HBox();
        metaRow.setAlignment(Pos.CENTER_LEFT);
        HBox spacer = new HBox();
        HBox.setHgrow(spacer, javafx.scene.layout.Priority.ALWAYS);
        metaRow.getChildren().addAll(courseBadge, spacer, memberPill);

        // Group title
        Label titleLabel = new Label(group.name);
        titleLabel.setWrapText(true);
        titleLabel.setStyle("-fx-font-size: 19; -fx-font-weight: bold; -fx-text-fill: #101828;");

        // Checkbox selection row (replaces the old instant-join button)
        CheckBox checkBox = new CheckBox(group.isMember ? "Selected" : "Select this group");
        checkBox.setSelected(group.isMember);
        checkBox.setStyle("-fx-font-size: 13; -fx-text-fill: #101828;");
        checkBox.selectedProperty().addListener((obs, wasSelected, isSelected) ->
                checkBox.setText(isSelected ? "Selected" : "Select this group"));
        checkboxesByGroupId.put(group.id, checkBox);

        HBox checkRow = new HBox(checkBox);
        checkRow.setAlignment(Pos.CENTER);
        checkRow.setPadding(new Insets(10, 0, 0, 0));

        card.getChildren().addAll(metaRow, titleLabel, checkRow);
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