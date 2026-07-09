package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.stage.Stage;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class SettingsController {

    @FXML private TextField nameField;
    @FXML private TextField emailField;
    @FXML private PasswordField currentPasswordField;
    @FXML private PasswordField newPasswordField;
    @FXML private PasswordField confirmPasswordField;
    @FXML private Label statusLabel;
    @FXML private Label errorLabel;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;

        nameField.setText(SessionManager.fullName);
        emailField.setText(SessionManager.userEmail);
    }

    @FXML
    protected void onSave() {
        hideMessages();

        String newPassword = newPasswordField.getText();
        String confirmPassword = confirmPasswordField.getText();

        if (!newPassword.isEmpty() && !newPassword.equals(confirmPassword)) {
            showError("New password and confirmation don't match.");
            return;
        }

        String name = nameField.getText();
        String email = emailField.getText();
        String currentPassword = currentPasswordField.getText();

        new Thread(() -> {
            try {
                URL url = URI.create("http://localhost:8000/api/settings").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("PUT");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);

                StringBuilder json = new StringBuilder("{");
                json.append("\"name\":\"").append(escapeJson(name)).append("\",");
                json.append("\"email\":\"").append(escapeJson(email)).append("\"");
                if (!newPassword.isEmpty()) {
                    json.append(",\"current_password\":\"").append(escapeJson(currentPassword)).append("\"");
                    json.append(",\"new_password\":\"").append(escapeJson(newPassword)).append("\"");
                }
                json.append("}");

                try (OutputStream os = conn.getOutputStream()) {
                    byte[] input = json.toString().getBytes(StandardCharsets.UTF_8);
                    os.write(input, 0, input.length);
                }

                int code = conn.getResponseCode();
                if (code == 200) {
                    SessionManager.fullName = name;
                    SessionManager.userEmail = email;
                    Platform.runLater(() -> showStatus("Settings updated successfully."));
                } else {
                    Platform.runLater(() -> showError("Could not update settings (check current password if changing it)."));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showError("Server error: " + e.getMessage()));
            }
        }).start();
    }

    @FXML
    protected void onLogout() {
        new Thread(() -> {
            try {
                URL url = URI.create("http://localhost:8000/api/logout").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Accept", "application/json");
                conn.getResponseCode();
            } catch (Exception e) {
                System.err.println("[Settings] Logout request failed (proceeding locally anyway): " + e.getMessage());
            }

            Platform.runLater(() -> {
                SessionManager.token = "";
                SessionManager.userId = 0;
                SessionManager.userEmail = "";
                SessionManager.fullName = "";
                loadLogin();
            });
        }).start();
    }

    private void loadLogin() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("login-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            LoginController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) nameField.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Campus Login");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load(), 900, 650);
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) nameField.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Dashboard");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void showStatus(String msg) {
        statusLabel.setText(msg);
        statusLabel.setVisible(true);
        statusLabel.setManaged(true);
    }

    private void showError(String msg) {
        errorLabel.setText(msg);
        errorLabel.setVisible(true);
        errorLabel.setManaged(true);
    }

    private void hideMessages() {
        statusLabel.setVisible(false);
        statusLabel.setManaged(false);
        errorLabel.setVisible(false);
        errorLabel.setManaged(false);
    }

    private String escapeJson(String input) {
        if (input == null) return "";
        return input.replace("\\", "\\\\").replace("\"", "\\\"");
    }
}
