package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
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

        // 3. Set up the device session using your database method signature
        int loggedInUserId = 1;
        dbManager.ensureDeviceState(loggedInUserId);

        // 4. Load your login interface layout
        FXMLLoader fxmlLoader = new FXMLLoader(HelloApplication.class.getResource("login-view.fxml"));
        Scene scene = new Scene(fxmlLoader.load(), 800, 600);

        // 5. Pass your initialized engines to the login controller
        LoginController loginController = fxmlLoader.getController();
        loginController.setServices(dbManager, syncService);

        // 6. Display the stage window
        stage.setTitle("DiscussionHub — Campus Login");
        stage.setScene(scene);
        stage.show();

        // 7. Fire up your background sync scheduler
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