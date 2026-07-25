package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.WindowUtil;
import com.discussionhub.client.utils.AppConfig;
import javafx.animation.Animation;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Bounds;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.stage.Popup;
import javafx.stage.Stage;
import javafx.util.Duration;
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

    private static final String BASE_URL = AppConfig.BASE_URL;
    private static final Duration NOTIFICATION_POLL_INTERVAL = Duration.seconds(15);

    @FXML private StackPane root;
    @FXML private VBox collapsiblePanel;
    @FXML private HBox brandRow;
    @FXML private Button toggleBtn;
    @FXML private Label brandIconLabel;
    @FXML private Label brandTextLabel;
    @FXML private StackPane bellWrapper;
    @FXML private Label notificationBadge;
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
    private Timeline notificationTimeline;
    private JSONArray lastNotifications = new JSONArray();
    private Popup notificationPopup;
    private VBox notificationListBox;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        applyCollapsedState();
        applyRoleVisibility();
        startNotificationPolling();
    }

    // Desktop-only: a Lecturer's nav is trimmed to Dashboard/Topic
    // Discussions/Settings - the other items (My Questions, Group Chat,
    // Marks, Quizzes, Recommend) are student-specific features a lecturer
    // account has no use for here. Same styling/layout as the student
    // sidebar throughout, just fewer buttons - not a separate look. The web
    // lecturer UI is untouched; this only ever runs on the desktop client.
    private void applyRoleVisibility() {
        if (!"Lecturer".equalsIgnoreCase(SessionManager.role)) return;

        for (Button btn : new Button[]{myQuestionsBtn, groupChatBtn, marksBtn, quizzesBtn, recommendBtn}) {
            btn.setVisible(false);
            btn.setManaged(false);
        }
    }

    // Lets a host screen run cleanup (stop polling timers, disconnect a
    // live socket) before the sidebar swaps the scene out from under it -
    // Group Chat needs this to disconnect its Pusher connection and cancel
    // its auto-refresh timeline, which "← Back" used to do inline.
    public void setOnBeforeNavigate(Runnable beforeNavigate) {
        this.beforeNavigate = beforeNavigate;
    }

    private void navigate() {
        // Every fx:include of sidebar.fxml builds a brand new
        // SidebarController - without this, the old instance's polling
        // Timeline would keep ticking forever in the background (JavaFX
        // Animations don't auto-stop just because their nodes left the
        // scene graph), leaking one more live timer per navigation.
        stopNotificationPolling();
        if (beforeNavigate != null) beforeNavigate.run();
    }

    // ---- Sidebar collapse ------------------------------------------------

    @FXML
    protected void onToggleSidebar() {
        SessionManager.sidebarCollapsed = !SessionManager.sidebarCollapsed;
        applyCollapsedState();
    }

    // Re-applied on every setServices() call (i.e. every screen load) since
    // SessionManager.sidebarCollapsed is the only thing that survives across
    // the fresh SidebarController each fx:include creates. Matches the web's
    // body.sidebar-collapsed .sidebar-panel { width: 0 } - collapsiblePanel
    // (brand, bell, nav buttons) disappears completely; the floating
    // toggleBtn never does, so there's always a way to bring it back.
    private void applyCollapsedState() {
        boolean collapsed = SessionManager.sidebarCollapsed;
        collapsiblePanel.setVisible(!collapsed);
        collapsiblePanel.setManaged(!collapsed);
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

    // ---- Notification bell ------------------------------------------------
    // Mirrors layouts/sidebar-notifications-script.blade.php: poll for the
    // unread count + latest 10 every few seconds, show a badge, and open a
    // dropdown of them on click with per-item and mark-all read actions.

    private void startNotificationPolling() {
        pollNotifications();
        if (notificationTimeline == null) {
            notificationTimeline = new Timeline(new KeyFrame(NOTIFICATION_POLL_INTERVAL, e -> pollNotifications()));
            notificationTimeline.setCycleCount(Animation.INDEFINITE);
        }
        notificationTimeline.playFromStart();
    }

    // Public so SettingsController's logout flow (which swaps to the login
    // screen directly, not through any of this sidebar's own onOpen*
    // methods) can also stop this before the sidebar instance is discarded.
    public void stopNotificationPolling() {
        if (notificationTimeline != null) {
            notificationTimeline.stop();
        }
    }

    private static final String NOTIFICATIONS_ENDPOINT = "/api/notifications/poll";

    private void pollNotifications() {
        new Thread(() -> {
            String body = get(NOTIFICATIONS_ENDPOINT);
            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    if (dbManager != null) dbManager.cacheApiResponse(NOTIFICATIONS_ENDPOINT, body);
                    applyNotifications(json);
                    return;
                } catch (Exception e) {
                    System.err.println("[Sidebar] Notification poll error: " + e.getMessage());
                }
            }

            // Offline (or unreachable): fall back to the last successfully
            // polled snapshot so the bell/badge/list still show real data
            // on a cold start, not just an empty "no notifications" state.
            if (dbManager == null) return;
            String cached = dbManager.getCachedApiResponse(NOTIFICATIONS_ENDPOINT);
            if (cached == null) return;
            try {
                applyNotifications(new JSONObject(cached));
            } catch (Exception e) {
                System.err.println("[Sidebar] Cached notification parse error: " + e.getMessage());
            }
        }).start();
    }

    private void applyNotifications(JSONObject json) {
        int unread = json.optInt("unread_count", 0);
        JSONArray notifications = json.optJSONArray("notifications");
        lastNotifications = notifications != null ? notifications : new JSONArray();
        Platform.runLater(() -> {
            updateBadge(unread);
            if (notificationListBox != null) renderNotificationList(notificationListBox);
        });
    }

    private void updateBadge(int unread) {
        if (unread > 0) {
            notificationBadge.setText(unread > 99 ? "99+" : String.valueOf(unread));
            notificationBadge.setVisible(true);
            notificationBadge.setManaged(true);
        } else {
            notificationBadge.setVisible(false);
            notificationBadge.setManaged(false);
        }
    }

    @FXML
    protected void onBellClicked() {
        if (notificationPopup != null && notificationPopup.isShowing()) {
            notificationPopup.hide();
            notificationListBox = null;
            return;
        }
        showNotificationPopup();
        // Mirrors sidebar-notifications-script.blade.php: opening the
        // dropdown means the user has seen the notifications, so the
        // unread badge clears immediately instead of waiting for
        // "Mark all read" or an individual click.
        updateBadge(0);
        onMarkAllRead();
    }

    private void showNotificationPopup() {
        VBox content = new VBox();
        content.setPrefWidth(300);
        content.setMaxWidth(300);
        // A Popup's content gets its own Scene, separate from the main
        // window's - it doesn't inherit app-theme.css just because the
        // sidebar that opened it has it. The custom -surface-card /
        // -surface-border vars below are declared on ".root" in
        // app-theme.css, and only the real Scene root normally gets that
        // style class auto-attached - Popup content never does. Without
        // BOTH the stylesheet attached AND "root" added explicitly here,
        // every -fx-background-color: -surface-card lookup (and every
        // .muted-text/.heading-text styleClass rule) silently fails to
        // resolve, so the card renders with NO background at all: fully
        // transparent, letting the sidebar's own "Dashboard" button show
        // straight through behind the "Notifications" header text.
        content.getStyleClass().add("root");
        content.getStylesheets().add(getClass().getResource("app-theme.css").toExternalForm());
        content.setStyle("-fx-background-color: -surface-card; -fx-background-radius: 10; " +
            "-fx-border-color: -surface-border; -fx-border-radius: 10; -fx-effect: dropshadow(gaussian, rgba(16,24,40,0.14), 12, 0, 0, 4);");

        HBox header = new HBox();
        header.setAlignment(Pos.CENTER_LEFT);
        header.setStyle("-fx-padding: 12 16; -fx-border-color: transparent transparent -surface-border transparent; -fx-border-width: 0 0 1 0;");
        Label headerTitle = new Label("Notifications");
        headerTitle.getStyleClass().add("heading-text");
        headerTitle.setStyle("-fx-font-size: 13;");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        Label markAll = new Label("Mark all read");
        markAll.setStyle("-fx-text-fill: -luna-mid; -fx-font-size: 11; -fx-font-weight: bold; -fx-cursor: hand;");
        markAll.setOnMouseClicked(e -> onMarkAllRead());
        header.getChildren().addAll(headerTitle, spacer, markAll);

        notificationListBox = new VBox();
        ScrollPane scrollPane = new ScrollPane(notificationListBox);
        scrollPane.setFitToWidth(true);
        scrollPane.setPrefHeight(320);
        scrollPane.setMaxHeight(320);
        scrollPane.setStyle("-fx-background-color: transparent; -fx-background-insets: 0;");

        content.getChildren().addAll(header, scrollPane);
        renderNotificationList(notificationListBox);

        notificationPopup = new Popup();
        notificationPopup.setAutoHide(true);
        notificationPopup.setOnAutoHide(e -> notificationListBox = null);
        notificationPopup.getContent().add(content);

        Bounds bounds = bellWrapper.localToScreen(bellWrapper.getBoundsInLocal());
        notificationPopup.show(bellWrapper, Math.max(8, bounds.getMaxX() - 300), bounds.getMaxY() + 6);
    }

    private void renderNotificationList(VBox list) {
        list.getChildren().clear();
        if (lastNotifications.isEmpty()) {
            Label empty = new Label("No notifications yet.");
            empty.getStyleClass().add("muted-text");
            empty.setStyle("-fx-padding: 16;");
            list.getChildren().add(empty);
            return;
        }
        for (int i = 0; i < lastNotifications.length(); i++) {
            JSONObject n = lastNotifications.getJSONObject(i);
            list.getChildren().add(buildNotificationRow(n));
        }
    }

    private VBox buildNotificationRow(JSONObject n) {
        boolean read = n.optBoolean("status", false);
        VBox row = new VBox(4);
        row.setStyle("-fx-padding: 10 16; -fx-background-color: " + (read ? "-surface-card" : "-luna-lightest") +
            "; -fx-border-color: transparent transparent -surface-border transparent; -fx-border-width: 0 0 1 0; -fx-cursor: hand;");

        Label message = new Label(n.optString("message", ""));
        message.setWrapText(true);
        message.setStyle("-fx-font-size: 12; -fx-text-fill: -text-body;");

        Label time = new Label(n.optString("time", ""));
        time.getStyleClass().add("muted-text");
        time.setStyle("-fx-font-size: 10;");

        row.getChildren().addAll(message, time);
        row.setOnMouseClicked(e -> onNotificationClicked(n.optInt("id", -1), row));
        return row;
    }

    private void onNotificationClicked(int id, VBox row) {
        if (id < 0) return;
        row.setStyle(row.getStyle().replace("-luna-lightest", "-surface-card"));

        // Update the badge immediately instead of waiting up to
        // NOTIFICATION_POLL_INTERVAL (15s) for the next poll to catch up -
        // onMarkAllRead() already re-polls right away for the same reason,
        // this just does it locally/instantly for a single notification
        // instead of a round-trip. Recomputed from lastNotifications rather
        // than parsed off the badge's own text, since that caps at "99+"
        // (not a number) and may still be empty/unset before the first poll.
        for (int i = 0; i < lastNotifications.length(); i++) {
            JSONObject n = lastNotifications.getJSONObject(i);
            if (n.optInt("id", -1) == id) {
                n.put("status", true);
                break;
            }
        }
        int unread = 0;
        for (int i = 0; i < lastNotifications.length(); i++) {
            if (!lastNotifications.getJSONObject(i).optBoolean("status", false)) unread++;
        }
        updateBadge(unread);

        new Thread(() -> post("/api/notifications/" + id + "/read")).start();
    }

    private void onMarkAllRead() {
        new Thread(() -> {
            post("/api/notifications/read-all");
            Platform.runLater(this::pollNotifications);
        }).start();
    }

    private void post(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            conn.getResponseCode();
        } catch (Exception e) {
            System.err.println("[Sidebar] Notification action error: " + e.getMessage());
        }
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
        navigate();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("marks-view.fxml"));
            Scene scene = new Scene(loader.load());
            MarksController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — Marks");
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening Marks: " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenQuizzes() {
        navigate();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("quizzes-view.fxml"));
            Scene scene = new Scene(loader.load());
            QuizzesController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — Quizzes");
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening Quizzes: " + e.getMessage());
        }
    }

    @FXML
    protected void onOpenRecommend() {
        navigate();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("recommend-view.fxml"));
            Scene scene = new Scene(loader.load());
            RecommendController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            WindowUtil.applyScene(stage(), scene, "DiscussionHub — Recommend");
        } catch (Exception e) {
            System.err.println("[Sidebar] Error opening recommend: " + e.getMessage());
        }
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
        // Only needs a live call to pick WHICH group's chat to default
        // into - once open, GroupChatController already has its own
        // offline handling (loadConversation() -> getCachedMessages(),
        // queuePendingMessage() for sending). This used to hard-refuse to
        // even navigate there at all when offline, which meant that
        // already-working offline chat experience was completely
        // unreachable from a cold, offline app launch.
        if (!com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable()) {
            if (SessionManager.lastGroupId != -1) {
                openGroupChat(SessionManager.lastGroupId, SessionManager.lastGroupName);
            } else {
                showNoConnection();
            }
            return;
        }

        groupChatBtn.setDisable(true);
        new Thread(() -> {
            String body = get("/api/dashboard");
            Platform.runLater(() -> {
                groupChatBtn.setDisable(false);
                if (body == null) {
                    if (SessionManager.lastGroupId != -1) {
                        openGroupChat(SessionManager.lastGroupId, SessionManager.lastGroupName);
                    } else {
                        showNoConnection();
                    }
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
        SessionManager.lastGroupId = groupId;
        SessionManager.lastGroupName = groupName;
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
        Alert alert = new Alert(Alert.AlertType.WARNING,
            "Group Chat needs an internet connection the first time, to know which of your groups to open.");
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
