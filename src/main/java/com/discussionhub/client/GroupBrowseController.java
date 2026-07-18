package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Platform;
import javafx.geometry.Pos;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

// Mirrors resources/views/groups/index.blade.php ("View Discussion Groups"):
// search, a Trending Groups section ranked by the Python ML gateway, and the
// full group list, each card showing a real derived course code via the
// shared GroupBrowseService (GET /api/groups/browse) instead of the desktop's
// old hardcoded 4-entry course-code lookup.
public class GroupBrowseController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    @FXML private TextField searchField;
    @FXML private Label statusLabel;
    @FXML private Label trendingHeadingLabel;
    @FXML private FlowPane groupsFlowPane;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("dashboard");
        loadGroups("");
    }

    @FXML
    protected void onSearch() {
        String query = searchField.getText() == null ? "" : searchField.getText().trim();
        loadGroups(query);
    }

    // Cache key is always the unfiltered listing - search itself isn't
    // reproducible offline (nothing server-side to search against), but the
    // full group list is exactly what's needed to at least browse/see
    // membership status without a connection.
    private static final String BROWSE_ENDPOINT = "/api/groups/browse";

    private void loadGroups(String search) {
        new Thread(() -> {
            String path = BROWSE_ENDPOINT + (search.isEmpty() ? "" : "?search=" + java.net.URLEncoder.encode(search, StandardCharsets.UTF_8));
            String body = get(path);
            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    if (search.isEmpty()) dbManager.cacheApiResponse(BROWSE_ENDPOINT, body);
                    Platform.runLater(() -> render(json));
                    return;
                } catch (Exception e) {
                    System.err.println("[GroupBrowse] Parse error: " + e.getMessage());
                }
            }

            String cached = dbManager.getCachedApiResponse(BROWSE_ENDPOINT);
            if (cached != null) {
                try {
                    JSONObject json = new JSONObject(cached);
                    Platform.runLater(() -> {
                        render(json);
                        if (!search.isEmpty()) {
                            showStatus("You're offline — search isn't available, showing all saved groups instead.");
                        }
                    });
                    return;
                } catch (Exception e) {
                    System.err.println("[GroupBrowse] Cached response parse error: " + e.getMessage());
                }
            }
            Platform.runLater(() -> showStatus("Couldn't load groups — check your connection."));
        }).start();
    }

    private void render(JSONObject json) {
        statusLabel.setVisible(false);
        statusLabel.setManaged(false);

        JSONArray trending = json.getJSONArray("trending_groups");
        trendingHeadingLabel.setVisible(!trending.isEmpty());
        trendingHeadingLabel.setManaged(!trending.isEmpty());

        groupsFlowPane.getChildren().clear();
        for (int i = 0; i < trending.length(); i++) {
            groupsFlowPane.getChildren().add(createGroupCard(trending.getJSONObject(i), true));
        }

        JSONArray groups = json.getJSONArray("groups");
        for (int i = 0; i < groups.length(); i++) {
            groupsFlowPane.getChildren().add(createGroupCard(groups.getJSONObject(i), false));
        }

        if (trending.isEmpty() && groups.isEmpty()) {
            showStatus("No groups match your search.");
        }
    }

    private VBox createGroupCard(JSONObject group, boolean isTrending) {
        boolean isMember = group.getBoolean("is_member");
        String courseCode = group.getString("course_code");

        VBox card = new VBox(10);
        card.getStyleClass().add("group-card");
        card.setAlignment(Pos.CENTER);
        card.setPrefWidth(220);
        card.setStyle("-fx-padding: 20 16;");

        HBox badgeRow = new HBox(6);
        badgeRow.setAlignment(Pos.CENTER);
        if (isTrending) {
            Label trendingBadge = new Label("Trending");
            trendingBadge.setStyle("-fx-background-color: #FBF0E1; -fx-text-fill: #D98E3D; "
                + "-fx-padding: 3 10; -fx-background-radius: 999; -fx-font-size: 10.5; -fx-font-weight: bold;");
            badgeRow.getChildren().add(trendingBadge);
        }
        Label codeBadge = new Label(courseCode);
        String codeBg = isMember ? "#FBE7E5" : "#EAF1F4";
        String codeFg = isMember ? "#D9483D" : "#26658C";
        codeBadge.setStyle(String.format(
            "-fx-background-color: %s; -fx-text-fill: %s; -fx-padding: 3 10; "
                + "-fx-background-radius: 999; -fx-font-size: 10.5; -fx-font-weight: bold;",
            codeBg, codeFg));
        badgeRow.getChildren().add(codeBadge);

        Label icon = new Label("👥");
        icon.getStyleClass().add("group-card-icon");
        icon.setStyle("-fx-padding: 10 14; -fx-font-size: 16;");

        Label nameLabel = new Label(group.getString("name"));
        nameLabel.getStyleClass().add("heading-text");
        nameLabel.setStyle("-fx-font-size: 15;");
        nameLabel.setWrapText(true);
        nameLabel.setAlignment(Pos.CENTER);

        int memberCount = group.optInt("member_count", 0);
        Label memberLabel = new Label(memberCount + (memberCount == 1 ? " member" : " members"));
        memberLabel.getStyleClass().add("muted-text");

        Button actionBtn = new Button(isMember ? "Leave Group  →" : "Join Group  →");
        actionBtn.getStyleClass().add(isMember ? "btn-outline-danger" : "btn-primary");
        actionBtn.setMaxWidth(Double.MAX_VALUE);
        int groupId = group.getInt("id");
        actionBtn.setOnAction(e -> {
            actionBtn.setDisable(true);
            if (isMember) {
                leaveGroup(groupId);
            } else {
                joinGroup(groupId);
            }
        });

        card.getChildren().addAll(badgeRow, icon, nameLabel, memberLabel, actionBtn);
        return card;
    }

    private void joinGroup(int groupId) {
        new Thread(() -> {
            boolean ok = post("/api/groups/" + groupId + "/join");
            Platform.runLater(() -> {
                if (ok) {
                    loadGroups(searchField.getText() == null ? "" : searchField.getText().trim());
                } else {
                    showStatus("Failed to join that group — check your connection.");
                }
            });
        }).start();
    }

    private void leaveGroup(int groupId) {
        new Thread(() -> {
            boolean ok = delete("/api/groups/" + groupId + "/leave");
            Platform.runLater(() -> {
                if (ok) {
                    loadGroups(searchField.getText() == null ? "" : searchField.getText().trim());
                } else {
                    showStatus("Failed to leave that group — check your connection.");
                }
            });
        }).start();
    }

    private void showStatus(String msg) {
        statusLabel.setText(msg);
        statusLabel.setVisible(true);
        statusLabel.setManaged(true);
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
            System.err.println("[GroupBrowse] GET error: " + e.getMessage());
            return null;
        }
    }

    private boolean post(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            int code = conn.getResponseCode();
            return code == 200 || code == 201;
        } catch (Exception e) {
            System.err.println("[GroupBrowse] POST error: " + e.getMessage());
            return false;
        }
    }

    private boolean delete(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("DELETE");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            int code = conn.getResponseCode();
            return code == 200 || code == 204;
        } catch (Exception e) {
            System.err.println("[GroupBrowse] DELETE error: " + e.getMessage());
            return false;
        }
    }
}
