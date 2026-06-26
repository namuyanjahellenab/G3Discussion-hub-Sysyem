package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.stage.Stage;

import java.io.IOException;

public class HelloApplication extends Application {

    @Override
    public void start(Stage stage) throws IOException {
        // 1. Create instance and initialize SQLite local tables
        DatabaseManager dbManager = new DatabaseManager();
        dbManager.initializeDatabase();

        //Perform real-time live network check
        boolean currentNetworkStatus = NetworkUtil.isNetworkAvailable();

        System.out.println("\n=== LIVE NETWORK DISCOVERY CHECK ===");
        if (currentNetworkStatus) {
            System.out.println("[Status] Network Status: ONLINE. Ready to communicate with Laravel backend API.");
        } else {
            System.out.println("[Status] Network Status: OFFLINE. Switching to local SQLite isolation mode.");
        }

        // 3. Intercept a sample post using the real, live network condition
        String author = "Mucuzi Isaac";
        String topicCategory = "Academic";

        dbManager.handleTopicSubmission(
                "Dynamic Network Detection Integration Testing",
                topicCategory,
                author,
                currentNetworkStatus
        );

        // 4. Load the JavaFX Graphic interface window
        FXMLLoader fxmlLoader = new FXMLLoader(HelloApplication.class.getResource("hello-view.fxml"));
        Scene scene = new Scene(fxmlLoader.load(), 320, 240);
        stage.setTitle("DiscussionHub - Sync Client Engine");
        stage.setScene(scene);
        stage.show();
    }

    public static void main(String[] args) {
        launch();
    }
}