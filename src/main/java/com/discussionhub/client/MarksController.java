package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import com.discussionhub.client.utils.WindowUtil;
import com.discussionhub.client.utils.AppConfig;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Label;
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

// Mirrors resources/views/marks/index.blade.php: one panel per group with
// two stat cards (Participation w/ posts+replies breakdown, Quiz
// Performance w/ average and "No quizzes attempted yet." fallback), that
// group's own quiz-result rows, and the right profile/tip panel.
// Previously this screen was just SimpleListController.loadMarks() - three
// flat stat tiles in a row and no right panel at all.
public class MarksController {

    private static final String BASE_URL = AppConfig.BASE_URL;

    @FXML private Label syncStatusLabel;
    @FXML private VBox groupsBox;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private SidebarController sidebarController;

    private static final String MARKS_ENDPOINT = "/api/marks";

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("marks");

        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
        userRoleLabel.setText((SessionManager.role == null || SessionManager.role.isBlank() ? "Student" : SessionManager.role) + " Account");

        load();
    }

    private void load() {
        Label loading = new Label("Loading…");
        loading.getStyleClass().add("muted-text");
        groupsBox.getChildren().setAll(loading);

        new Thread(() -> {
            String body = get(MARKS_ENDPOINT);
            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    dbManager.cacheApiResponse(MARKS_ENDPOINT, body);
                    Platform.runLater(() -> render(json.getJSONArray("groups")));
                    return;
                } catch (Exception e) {
                    System.err.println("[Marks] Parse error: " + e.getMessage());
                }
            }

            String cached = dbManager.getCachedApiResponse(MARKS_ENDPOINT);
            if (cached != null) {
                try {
                    JSONObject json = new JSONObject(cached);
                    Platform.runLater(() -> {
                        render(json.getJSONArray("groups"));
                        showOfflineBanner();
                    });
                    return;
                } catch (Exception e) {
                    System.err.println("[Marks] Cached response parse error: " + e.getMessage());
                }
            }
            Platform.runLater(() -> showMessage("Couldn't reach the server — check your connection."));
        }).start();
    }

    private void render(JSONArray groups) {
        if (groups.isEmpty()) {
            showMessage("Join a group to start earning participation and quiz marks.");
            return;
        }

        VBox box = new VBox(16);
        for (int i = 0; i < groups.length(); i++) {
            box.getChildren().add(groupPanel(groups.getJSONObject(i)));
        }
        groupsBox.getChildren().setAll(box);
    }

    private VBox groupPanel(JSONObject group) {
        VBox panel = new VBox();
        panel.getStyleClass().add("panel-card");

        HBox header = new HBox();
        header.setAlignment(Pos.CENTER_LEFT);
        header.setStyle("-fx-padding: 16 20; -fx-border-color: transparent transparent -surface-border transparent; -fx-border-width: 0 0 1 0;");
        Label groupName = new Label(group.getString("group_name"));
        groupName.getStyleClass().add("heading-text");
        groupName.setStyle("-fx-font-size: 15;");
        header.getChildren().add(groupName);

        HBox statsRow = new HBox(16);
        statsRow.setStyle("-fx-padding: 20;");

        statsRow.getChildren().add(participationStat(group));
        statsRow.getChildren().add(quizPerformanceStat(group));

        panel.getChildren().addAll(header, statsRow);

        JSONArray quizzes = group.optJSONArray("quizzes");
        if (quizzes != null && !quizzes.isEmpty()) {
            for (int i = 0; i < quizzes.length(); i++) {
                panel.getChildren().add(quizRow(quizzes.getJSONObject(i)));
            }
        }

        return panel;
    }

    private VBox statCard(String iconStyle, String title) {
        VBox card = new VBox(6);
        HBox.setHgrow(card, Priority.ALWAYS);

        Label icon = new Label("●");
        icon.setMinSize(40, 40);
        icon.setMaxSize(40, 40);
        icon.setAlignment(Pos.CENTER);
        icon.setStyle(iconStyle + " -fx-background-radius: 10; -fx-font-size: 14;");

        Label titleLabel = new Label(title);
        titleLabel.getStyleClass().add("heading-text");
        titleLabel.setStyle("-fx-font-size: 13;");

        card.getChildren().addAll(icon, titleLabel);
        return card;
    }

    private VBox participationStat(JSONObject group) {
        VBox card = statCard("-fx-background-color: derive(-accent-success, 85%); -fx-text-fill: -accent-success;", "Participation");

        Label value = new Label(fmtNumber(group.optDouble("participation", 0)) + "/10");
        value.setStyle("-fx-font-size: 20; -fx-font-weight: bold; -fx-text-fill: -text-heading;");

        Label sub = new Label("Posts: " + group.optInt("post_count", 0) + "   Replies: " + group.optInt("reply_count", 0));
        sub.getStyleClass().add("muted-text");
        sub.setWrapText(true);
        sub.setStyle("-fx-font-size: 11.5;");

        card.getChildren().addAll(value, sub);
        return card;
    }

    private VBox quizPerformanceStat(JSONObject group) {
        VBox card = statCard("-fx-background-color: -luna-lightest; -fx-text-fill: -luna-dark;", "Quiz Performance");

        if (!group.isNull("quiz_average")) {
            Label value = new Label(fmtNumber(group.optDouble("quiz_average", 0)) + "%");
            value.setStyle("-fx-font-size: 20; -fx-font-weight: bold; -fx-text-fill: -text-heading;");
            Label sub = new Label("Quizzes taken: " + group.optInt("quizzes_taken", 0));
            sub.getStyleClass().add("muted-text");
            sub.setStyle("-fx-font-size: 11.5;");
            card.getChildren().addAll(value, sub);
        } else {
            Label empty = new Label("No quizzes attempted yet.");
            empty.getStyleClass().add("muted-text");
            empty.setWrapText(true);
            empty.setStyle("-fx-font-size: 12;");
            card.getChildren().add(empty);
        }

        return card;
    }

    private HBox quizRow(JSONObject r) {
        HBox row = new HBox(10);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-padding: 12 20; -fx-border-color: -surface-border transparent transparent transparent; -fx-border-width: 1 0 0 0;");

        VBox main = new VBox(2);
        HBox.setHgrow(main, Priority.ALWAYS);
        Label title = new Label(r.optString("title", "Deleted quiz"));
        title.getStyleClass().add("heading-text");
        title.setStyle("-fx-font-size: 13.5;");
        String metaText = r.optString("submitted_ago", "") + (r.optBoolean("auto_submitted", false) ? "  ·  auto-submitted" : "");
        Label meta = new Label(metaText);
        meta.getStyleClass().add("muted-text");
        meta.setStyle("-fx-font-size: 12;");
        main.getChildren().addAll(title, meta);

        Label score = new Label(fmtNumber(r.optDouble("score", 0)) + " pts");
        score.setStyle("-fx-font-weight: bold; -fx-text-fill: -luna-mid;");

        row.getChildren().addAll(main, score);
        return row;
    }

    private String fmtNumber(double m) {
        return (m == Math.floor(m)) ? String.valueOf((int) m) : String.valueOf(m);
    }

    private void showOfflineBanner() {
        syncStatusLabel.setText("● OFFLINE — showing saved marks");
        syncStatusLabel.setStyle("-fx-text-fill: #D9483D; -fx-font-size: 12; -fx-font-weight: bold;");
        syncStatusLabel.setVisible(true);
        syncStatusLabel.setManaged(true);
    }

    private void showMessage(String message) {
        Label empty = new Label(message);
        empty.getStyleClass().add("muted-text");
        empty.setWrapText(true);
        empty.setStyle("-fx-padding: 16 4;");
        groupsBox.getChildren().setAll(empty);
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
            System.err.println("[Marks] Error going back: " + e.getMessage());
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
            System.err.println("[Marks] Request error: " + e.getMessage());
            return null;
        }
    }
}
