package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.NetworkUtil;
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

        dbManager = new DatabaseManager();
        dbManager.initializeDatabase();

        // TODO: replace with the real logged-in UserID once login exists
        int loggedInUserId = 1;
        dbManager.ensureDeviceState(loggedInUserId);

        syncService = new DeltaSyncService(dbManager);

        boolean currentNetworkStatus = NetworkUtil.isNetworkAvailable();

        if (currentNetworkStatus) {
            syncService.synchronizeLocalChanges();
        } else {
            dbManager.updateDeviceSyncStatus("Offline");
        }

        FXMLLoader fxmlLoader = new FXMLLoader(HelloApplication.class.getResource("hello-view.fxml"));
        Scene scene = new Scene(fxmlLoader.load(), 320, 240);

        HelloController controller = fxmlLoader.getController();
        controller.setServices(dbManager, syncService);

        stage.setTitle("DiscussionHub - Sync Client Engine");
        stage.setScene(scene);
        stage.show();
        com.discussionhub.client.quiz.QuizPopupService.start(stage);
    }

    public static void main(String[] args) {
        launch();
    }
}