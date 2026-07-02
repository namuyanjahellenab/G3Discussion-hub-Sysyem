package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.PasswordField;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.stage.Stage;

public class LoginController {
    @FXML private TextField usernameField;
    @FXML private PasswordField passwordField;
    @FXML private Label errorLabel;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    // This method allows HelloApplication to pass your database and sync engines into this screen
    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
    }

    @FXML
    protected void onLoginClick() {
        String username = usernameField.getText().trim();
        String password = passwordField.getText().trim();

        if (username.isEmpty() || password.isEmpty()) {
            errorLabel.setText("Please enter both username and password.");
            errorLabel.setVisible(true);
            return;
        }

        try {
            int loggedInUserId = dbManager.verifyUser(username, password);

            if (loggedInUserId == -1) {
                errorLabel.setText("Invalid username or password.");
                errorLabel.setVisible(true);
                return;
            }

            // 1. Mark this user as authenticated and save their session in SQLite
            // (This leverages your DeviceState table logic)
            dbManager.ensureDeviceState(loggedInUserId);

            // 2. Load the Dashboard view layout
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);

            // 3. Pass the database services forward to the Dashboard controller
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            // 4. Switch the current window stage from Login to the Dashboard
            Stage stage = (Stage) usernameField.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Dashboard");

        } catch (Exception e) {
            System.err.println("[Login UI] Failed to transition to dashboard: " + e.getMessage());
            e.printStackTrace();
        }
    }
}