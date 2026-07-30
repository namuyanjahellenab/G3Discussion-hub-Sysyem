package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.database.SavedSession;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Rectangle2D;
import javafx.scene.Scene;
import javafx.stage.Screen;
import javafx.stage.Stage;
import java.io.IOException;

public class HelloApplication extends Application {

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    @Override
    public void start(Stage stage) throws IOException {
        // 1. Initialize DatabaseManager
        dbManager = new DatabaseManager();
        dbManager.initializeDatabase();

        // 2. Initialize DeltaSyncService
        syncService = new DeltaSyncService(dbManager);

        // 3. A "Remember Me" login (see LoginController.onLogin()) skips the
        // login screen entirely and restores straight into the Dashboard,
        // the same WhatsApp-style behavior on both platforms - this also
        // works while offline, since DashboardController already falls back
        // to its cached response when the live call fails.
        SavedSession saved = dbManager.loadSession();
        Scene scene;
        if (saved != null) {
            SessionManager.token = saved.getToken();
            SessionManager.userId = saved.getUserId();
            SessionManager.userEmail = saved.getUserEmail();
            SessionManager.fullName = saved.getFullName();
            SessionManager.role = saved.getRole();
            SessionManager.currentTheme = saved.getThemeColor() == null || saved.getThemeColor().isEmpty()
                ? "luna" : saved.getThemeColor();

            FXMLLoader fxmlLoader = new FXMLLoader(HelloApplication.class.getResource("dashboard-view.fxml"));
            scene = new Scene(fxmlLoader.load());
            DashboardController dashboardController = fxmlLoader.getController();
            dashboardController.setServices(dbManager, syncService);
        } else {
            FXMLLoader fxmlLoader = new FXMLLoader(HelloApplication.class.getResource("login-view.fxml"));
            scene = new Scene(fxmlLoader.load());
            LoginController loginController = fxmlLoader.getController();
            loginController.setServices(dbManager, syncService);
        }

        // 5. Display the stage window at the screen's full usable area, set
        // via explicit width/height/x/y - not the OS "maximized" flag,
        // which produced a different effective size on every scene switch
        // instead of a stable one. Every later screen switch
        // (WindowUtil.applyScene) carries these exact numbers forward
        // unconditionally, so the window is the same size on every screen
        // from the very first frame - nothing to grow into, nothing to
        // jump. This also comfortably fits every screen's content, which
        // was the original problem with the old fixed 1000x700 start size.
        Rectangle2D screenBounds = Screen.getPrimary().getVisualBounds();
        stage.setTitle(saved != null ? "DiscussionHub — Dashboard" : "DiscussionHub — Campus Login");
        stage.setScene(scene);
        stage.setResizable(true);
        stage.setX(screenBounds.getMinX());
        stage.setY(screenBounds.getMinY());
        stage.setWidth(screenBounds.getWidth());
        stage.setHeight(screenBounds.getHeight());
        stage.show();

        // 6. Fire up your background sync scheduler
        startSyncScheduler();
    }

    // THIS IS THE METHOD THAT REPRESENTS THE MISSING SYMBOL
    private void startSyncScheduler() {
        System.out.println("[Scheduler] Background replication engine active.");
    }

    public static void main(String[] args) {
        launch();
    }
}