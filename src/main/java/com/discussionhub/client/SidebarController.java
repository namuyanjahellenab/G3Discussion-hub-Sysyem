package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.WindowUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
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

// Controller for the shared sidebar.fxml, included via <fx:include> on every
// screen after login. Mirrors the web's layouts/sidebar-student.blade.php,
// which every student page shows by default. Every host controller must
// call setServices(...) right after fx:include loads (same pattern as
// passing dbManager/syncService to any other screen controller) and,
// optionally, setActive("forum") so the matching nav button highlights.
public class SidebarController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    @FXML private VBox root;
    @FXML private Button dashboardBtn;
    @FXML private Button forumBtn;
    @FXML private Button myQuestionsBtn;
    @FXML private Button groupChatBtn;
    @FXML private Button marksBtn;
    @FXML private Button quizzesBtn;
    @FXML private Button recommendBtn;
    @FXML private Button settingsBtn;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private Runnable beforeNavigate;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
    }

    // Lets a host screen run cleanup (stop polling timers, disconnect a
    // live socket) before the sidebar swaps the scene out from under it -
    // Group Chat needs this to disconnect its Pusher connection and cancel
    // its auto-refresh timeline, which "← Back" used to do inline.
    public void setOnBeforeNavigate(Runnable beforeNavigate) {
        this.beforeNavigate = beforeNavigate;
    }

    private void navigate() {
        if (beforeNavigate != null) beforeNavigate.run();
    }

    // screenKey: one of "dashboard", "forum", "myquestions", "groupchat",
    // "marks", "quizzes", "recommend", "settings" - matches the button that
    // should render as the active/current page.
    public void setActive(String screenKey) {
        for (Button b : new Button[]{dashboardBtn, forumBtn, myQuestionsBtn, groupChatBtn,
                marksBtn, quizzesBtn, recommendBtn, settingsBtn}) {
            b.getStyleClass().remove("sidebar-link-active");
            if (!b.getStyleClass().contains("sidebar-link")) {
                b.getStyleClass().add("sidebar-link");
            }
        }
        Button active = switch (screenKey) {
            case "dashboard" -> dashboardBtn;
            case "forum" -> forumBtn;
            case "myquestions" -> myQuestionsBtn;
            case "groupchat" -> groupChatBtn;
            case "marks" -> marksBtn;
            case "quizzes" -> quizzesBtn;
            case "recommend" -> recommendBtn;
            case "settings" -> settingsBtn;
            default -> null;
        };
        if (active != null) {
            active.getStyleClass().remove("sidebar-link");
            active.getStyleClass().add("sidebar-link-active");
        }
    }

    private Stage stage() {
        return (Stage) root.getScene().getWindow();
    }

    @FXML
    protected void onOpenDashboard() {
        navigate();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — Dashboard");
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening dashboard: " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenForum() {
        navigate();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("forum-view.fxml"));
            Scene scene = new Scene(loader.load());
            ForumController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — Forum");
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening forum: " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenMyQuestions() {
        openSimpleList("My Questions", SimpleListController::loadMyQuestions);
    }

    @FXML
    protected void onOpenMarks() {
        openSimpleList("Marks", SimpleListController::loadMarks);
    }

    @FXML
    protected void onOpenQuizzes() {
        openSimpleList("Quizzes", SimpleListController::loadQuizzes);
    }

    @FXML
    protected void onOpenRecommend() {
        openSimpleList("Recommend", SimpleListController::loadRecommend);
    }

    private void openSimpleList(String title, java.util.function.Consumer<SimpleListController> loader) {
        navigate();
        try {
            FXMLLoader fxmlLoader = new FXMLLoader(getClass().getResource("simple-list-view.fxml"));
            Scene scene = new Scene(fxmlLoader.load());
            SimpleListController controller = fxmlLoader.getController();
            controller.setServices(dbManager, syncService);
            loader.accept(controller);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — " + title);
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening " + title + ": " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenSettings() {
        navigate();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("settings-view.fxml"));
            Scene scene = new Scene(loader.load());
            SettingsController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — Settings");
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening settings: " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenGroupChat() {
        groupChatBtn.setDisable(true);
        new Thread(() -> {
            String body = get("/api/dashboard");
            Platform.runLater(() -> {
                groupChatBtn.setDisable(false);
                if (body == null) {
                    showNoConnection();
                    return;
                }
                try {
                    JSONArray groups = new JSONObject(body).getJSONArray("joined_groups");
                    if (groups.length() == 0) {
                        Alert alert = new Alert(Alert.AlertType.INFORMATION, "Join a group first to unlock its chat.");
                        alert.setHeaderText(null);
                        alert.showAndWait();
                        return;
                    }
                    JSONObject firstGroup = groups.getJSONObject(0);
                    openGroupChat(firstGroup.getInt("id"), firstGroup.getString("name"));
                } catch (Exception e) {
                    System.err.println("[Sidebar] Error parsing groups: " + e.getMessage());
                }
            });
        }).start();
    }

    private void openGroupChat(int groupId, String groupName) {
        navigate();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("group-chat-view.fxml"));
            Scene scene = new Scene(loader.load());
            GroupChatController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.setGroupContext(groupId, groupName);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — " + groupName + " Chat");
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening group chat: " + e.getMessage());
        }
    }

    private void showNoConnection() {
        Alert alert = new Alert(Alert.AlertType.WARNING, "Group Chat needs an internet connection.");
        alert.setHeaderText(null);
        alert.showAndWait();
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
            System.err.println("[Sidebar] Request error: " + e.getMessage());
            return null;
        }
    }
}
