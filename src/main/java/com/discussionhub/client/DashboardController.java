package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import javafx.animation.Animation;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
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
import javafx.util.Duration;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

// Mirrors DashboardController::index() + dashboard/index.blade.php on the
// web side: stat cards, My Groups panel, Notifications panel, profile mini,
// Upcoming Quiz, and the latest announcement - all from one GET to
// /api/dashboard. Nothing here that isn't on the web page.
public class DashboardController {

    private static final String BASE_URL = "http://127.0.0.1:8000";

    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private Label syncStatusLabel;
    @FXML private Label lastSyncLabel;
    @FXML private VBox pendingUploadsCard;
    @FXML private Label pendingCountLabel;
    @FXML private FlowPane groupsFlowPane;
    @FXML private VBox recentNotificationsBox;

    @FXML private Label statGroupsLabel;
    @FXML private Label statNotificationsLabel;
    @FXML private Label statParticipationLabel;
    @FXML private Label statQuizAvgLabel;
    @FXML private VBox participationCard;
    @FXML private VBox quizAvgCard;

    @FXML private Label upcomingQuizTitleLabel;
    @FXML private Label upcomingQuizMetaLabel;
    @FXML private Button upcomingQuizButton;
    @FXML private Label noUpcomingQuizLabel;

    @FXML private VBox announcementBanner;
    @FXML private Label announcementTagLabel;
    @FXML private Label announcementBodyLabel;

    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private List<GroupSummary> myGroups = new ArrayList<>();

    // Re-probes connectivity on a timer so the ONLINE/OFFLINE indicator
    // self-heals - previously this only ran once on screen load, so a
    // moment's slow response (or the dashboard just being left open) left it
    // stuck showing OFFLINE until the user manually hit Retry Sync.
    private static final Duration STATUS_REFRESH_INTERVAL = Duration.seconds(10);
    private Timeline statusRefreshTimeline;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("dashboard");
        sidebarController.setOnBeforeNavigate(this::stopStatusAutoRefresh);
        loadUserProfile();
        loadDashboard();
        refreshSyncStatus();
        startStatusAutoRefresh();
    }

    private void startStatusAutoRefresh() {
        if (statusRefreshTimeline == null) {
            statusRefreshTimeline = new Timeline(new KeyFrame(STATUS_REFRESH_INTERVAL, e -> refreshSyncStatus()));
            statusRefreshTimeline.setCycleCount(Animation.INDEFINITE);
        }
        statusRefreshTimeline.playFromStart();
    }

    private void stopStatusAutoRefresh() {
        if (statusRefreshTimeline != null) {
            statusRefreshTimeline.stop();
        }
    }

    // Desktop-only (SDD figure 6.7, "Desktop offline interface") - shows
    // live connection state, last sync time, and pending offline changes.
    // The network probe is a real HTTP call (up to ~2.5s on a timeout), so it
    // runs off the FX thread - this used to block the UI on every dashboard
    // load and every 10s tick.
    private void refreshSyncStatus() {
        new Thread(() -> {
            boolean isOnline = com.discussionhub.client.utils.NetworkUtil.isNetworkAvailable();
            Platform.runLater(() -> applySyncStatus(isOnline));
        }).start();
    }

    private void applySyncStatus(boolean isOnline) {
        syncStatusLabel.setText(isOnline ? "● ONLINE" : "● OFFLINE");
        syncStatusLabel.setStyle("-fx-font-size: 12; -fx-font-weight: bold; -fx-text-fill: "
            + (isOnline ? "#3F9C6B" : "#D9483D") + ";");

        String lastSync = dbManager.getLastSyncTimestamp();
        lastSyncLabel.setText("Last synced: " + (lastSync != null ? lastSync : "never"));

        int pendingCount = dbManager.getPendingChanges().size();
        pendingCountLabel.setText(pendingCount + (pendingCount == 1 ? " item" : " items")
            + " waiting to sync");
        pendingUploadsCard.setVisible(pendingCount > 0 || !isOnline);
        pendingUploadsCard.setManaged(pendingCount > 0 || !isOnline);
    }

    @FXML
    protected void onRetrySync() {
        pendingCountLabel.setText("Syncing…");
        new Thread(() -> {
            syncService.synchronizeLocalChanges();
            Platform.runLater(() -> {
                refreshSyncStatus();
                loadDashboard();
            });
        }).start();
    }

    private void loadUserProfile() {
        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
        userRoleLabel.setText((SessionManager.role == null || SessionManager.role.isBlank() ? "Student" : SessionManager.role) + " Account");

        // Participation and Quiz Average are per-student metrics a lecturer
        // doesn't personally accumulate (they assign/grade these, not earn
        // them) - hidden for a Lecturer account, same as the sidebar trim.
        if ("Lecturer".equalsIgnoreCase(SessionManager.role)) {
            participationCard.setVisible(false);
            participationCard.setManaged(false);
            quizAvgCard.setVisible(false);
            quizAvgCard.setManaged(false);
        }
    }

    private static final String DASHBOARD_ENDPOINT = "/api/dashboard";

    private void loadDashboard() {
        new Thread(() -> {
            String body = get(DASHBOARD_ENDPOINT);
            if (body != null) {
                try {
                    JSONObject json = new JSONObject(body);
                    dbManager.cacheApiResponse(DASHBOARD_ENDPOINT, body);
                    Platform.runLater(() -> render(json));
                    return;
                } catch (Exception e) {
                    System.err.println("[Dashboard] Parse error: " + e.getMessage());
                }
            }
            // Offline, or the live call failed anyway - replay whatever
            // dashboard snapshot was last actually seen online, through the
            // exact same render() a live response uses, instead of a
            // separate (and inevitably out of sync) cache-only rendering
            // path. Genuinely nothing to show only on a first-ever offline
            // launch that never once loaded this screen while online.
            String cached = dbManager.getCachedApiResponse(DASHBOARD_ENDPOINT);
            if (cached != null) {
                try {
                    JSONObject json = new JSONObject(cached);
                    // "● OFFLINE" in the top-right (applySyncStatus, already
                    // wired to the 10s refreshSyncStatus() timer) already
                    // says this isn't live - no need for a second banner
                    // here just to repeat that.
                    Platform.runLater(() -> render(json));
                    return;
                } catch (Exception e) {
                    System.err.println("[Dashboard] Cached response parse error: " + e.getMessage());
                }
            }
            Platform.runLater(() -> groupsFlowPane.getChildren().setAll(offlineNotice()));
        }).start();
    }

    private Label offlineNotice() {
        Label l = new Label("You're offline, and this dashboard hasn't loaded successfully yet on this device — "
            + "once it does, it'll keep showing your last saved snapshot here even without a connection.");
        l.getStyleClass().add("muted-text");
        l.setWrapText(true);
        l.setStyle("-fx-padding: 16 4;");
        return l;
    }

    private void render(JSONObject json) {
        JSONArray groups = json.getJSONArray("joined_groups");
        myGroups = new ArrayList<>();
        groupsFlowPane.getChildren().clear();
        for (int i = 0; i < groups.length(); i++) {
            JSONObject g = groups.getJSONObject(i);
            GroupSummary group = new GroupSummary(g.getInt("id"), g.getString("name"), g.getInt("member_count"));
            myGroups.add(group);
            groupsFlowPane.getChildren().add(createGroupCard(group));
        }
        statGroupsLabel.setText(String.valueOf(groups.length()));

        statNotificationsLabel.setText(String.valueOf(json.getInt("notifications_count")));

        JSONArray notifications = json.getJSONArray("notifications");
        recentNotificationsBox.getChildren().clear();
        if (notifications.isEmpty()) {
            recentNotificationsBox.getChildren().add(emptyState("No notifications yet."));
        } else {
            for (int i = 0; i < notifications.length(); i++) {
                recentNotificationsBox.getChildren().add(createNotificationRow(notifications.getJSONObject(i)));
            }
        }

        JSONObject snapshot = json.getJSONObject("snapshot");
        statParticipationLabel.setText(snapshot.get("participation") + " / 10");
        statQuizAvgLabel.setText(snapshot.isNull("quiz_average") ? "—" : String.valueOf(snapshot.get("quiz_average")));

        boolean hasUpcomingQuiz = !json.isNull("upcoming_quiz");
        upcomingQuizTitleLabel.setVisible(hasUpcomingQuiz);
        upcomingQuizTitleLabel.setManaged(hasUpcomingQuiz);
        upcomingQuizMetaLabel.setVisible(hasUpcomingQuiz);
        upcomingQuizMetaLabel.setManaged(hasUpcomingQuiz);
        upcomingQuizButton.setVisible(hasUpcomingQuiz);
        upcomingQuizButton.setManaged(hasUpcomingQuiz);
        noUpcomingQuizLabel.setVisible(!hasUpcomingQuiz);
        noUpcomingQuizLabel.setManaged(!hasUpcomingQuiz);
        if (hasUpcomingQuiz) {
            JSONObject quiz = json.getJSONObject("upcoming_quiz");
            upcomingQuizTitleLabel.setText(quiz.getString("title"));
            upcomingQuizMetaLabel.setText(quiz.getString("start_time") + " · " + quiz.getInt("duration_minutes") + " min");
        }

        boolean hasAnnouncement = !json.isNull("latest_announcement");
        announcementBanner.setVisible(hasAnnouncement);
        announcementBanner.setManaged(hasAnnouncement);
        if (hasAnnouncement) {
            JSONObject announcement = json.getJSONObject("latest_announcement");
            String groupName = announcement.optString("group_name", null);
            announcementTagLabel.setText("📢 ANNOUNCEMENT" + (groupName != null ? " — " + groupName : ""));
            announcementBodyLabel.setText(announcement.getString("message"));
        }
    }

    /** Matches .group-card / .group-card-icon in dashboard/index.blade.php exactly. */
    private VBox createGroupCard(GroupSummary group) {
        VBox card = new VBox(10);
        card.getStyleClass().add("group-card");
        card.setAlignment(Pos.CENTER);
        card.setPrefWidth(210);
        card.setStyle("-fx-padding: 20 16;");

        Label icon = new Label("👥");
        icon.getStyleClass().add("group-card-icon");
        icon.setStyle("-fx-padding: 10 14; -fx-font-size: 16;");

        Label nameLabel = new Label(group.name);
        nameLabel.getStyleClass().add("heading-text");
        nameLabel.setStyle("-fx-font-size: 15;");

        Label memberLabel = new Label(group.memberCount + (group.memberCount == 1 ? " member" : " members"));
        memberLabel.getStyleClass().add("muted-text");

        Button viewForumBtn = new Button("View Forum  →");
        viewForumBtn.getStyleClass().add("btn-primary");
        viewForumBtn.setMaxWidth(Double.MAX_VALUE);
        viewForumBtn.setOnAction(e -> openForumForGroup(group));

        card.getChildren().addAll(icon, nameLabel, memberLabel, viewForumBtn);
        return card;
    }

    private VBox createNotificationRow(JSONObject n) {
        boolean isRead = n.optBoolean("is_read", false);

        VBox row = new VBox(2);
        row.setStyle("-fx-padding: 13 20; -fx-border-color: transparent transparent #E1E9ED transparent; " +
            "-fx-border-width: 0 0 1 0;" + (isRead ? "" : " -fx-cursor: hand;"));

        HBox topRow = new HBox(10);
        topRow.setAlignment(Pos.TOP_LEFT);

        Label dot = new Label("●");
        dot.setStyle("-fx-text-fill: " + (isRead ? "#E1E9ED" : "#26658C") + "; -fx-font-size: 8;");

        VBox textCol = new VBox(2);
        Label type = new Label(n.optString("type", "Notification"));
        type.getStyleClass().add("heading-text");
        type.setStyle("-fx-font-size: 13;");
        Label message = new Label(n.optString("message", ""));
        message.setWrapText(true);
        message.getStyleClass().add("muted-text");
        Label time = new Label(n.optString("time", ""));
        time.getStyleClass().add("muted-text");
        textCol.getChildren().addAll(type, message, time);

        HBox.setHgrow(textCol, Priority.ALWAYS);
        topRow.getChildren().addAll(dot, textCol);
        row.getChildren().add(topRow);

        if (!isRead && n.has("id")) {
            row.setOnMouseClicked(e -> markNotificationRead(n.getInt("id")));
        }
        return row;
    }

    private void markNotificationRead(int notificationId) {
        new Thread(() -> {
            post("/api/notifications/" + notificationId + "/read");
            Platform.runLater(this::loadDashboard);
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
            System.err.println("[Dashboard] POST " + path + " failed: " + e.getMessage());
        }
    }

    private Label emptyState(String text) {
        Label l = new Label(text);
        l.getStyleClass().add("muted-text");
        l.setStyle("-fx-padding: 24 20;");
        return l;
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
            System.err.println("[Dashboard] Request error: " + e.getMessage());
            return null;
        }
    }

    // Distinct from the sidebar's own Quizzes button - this is the "Upcoming
    // Quiz" card's "View Details" button, which needs the same destination.
    @FXML
    protected void onOpenQuizzes() {
        sidebarController.onOpenQuizzes();
    }

    // "See all" on the My Groups panel - mirrors the web's route('groups.index')
    // ("View Discussion Groups": search, Trending Groups, every group with a
    // real derived course code), distinct from the onboarding group-picker
    // that only LoginController opens for brand-new members.
    @FXML
    protected void onOpenGroupBrowse() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("group-browse-view.fxml"));
            Scene scene = new Scene(loader.load());
            GroupBrowseController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — View Discussion Groups");
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening group browse: " + e.getMessage());
        }
    }

    private void openForumForGroup(GroupSummary group) {
        SessionManager.lastGroupId = group.id;
        SessionManager.lastGroupName = group.name;
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("group-topics-view.fxml"));
            Scene scene = new Scene(loader.load());
            GroupTopicsController controller = loader.getController();
            controller.setServices(dbManager, syncService);
            controller.setGroupContext(group.id, group.name);

            Stage stage = (Stage) syncStatusLabel.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — " + group.name);
        } catch (Exception e) {
            System.err.println("[Dashboard] Error opening group forum: " + e.getMessage());
        }
    }

    private static class GroupSummary {
        final int id;
        final String name;
        final int memberCount;
        GroupSummary(int id, String name, int memberCount) {
            this.id = id;
            this.name = name;
            this.memberCount = memberCount;
        }
    }
}
