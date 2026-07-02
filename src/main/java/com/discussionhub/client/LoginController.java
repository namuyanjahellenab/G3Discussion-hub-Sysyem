package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.stage.Stage;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Scanner;

public class LoginController {

    @FXML private TextField emailField;
    @FXML private PasswordField passwordField;
    @FXML private TextField passwordVisible;
    @FXML private Button showPasswordBtn;
    @FXML private CheckBox rememberMeCheck;
    @FXML private Label errorLabel;
    @FXML private Label statusLabel;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
    }

    @FXML
    public void onLogin() {
        String email = emailField.getText();
        String password = passwordField.isVisible() ? passwordField.getText() : passwordVisible.getText();

        if (email.isEmpty() || password.isEmpty()) {
            errorLabel.setText("Please fill all fields.");
            errorLabel.setVisible(true);
            return;
        }

        new Thread(() -> {
            try {
                URL url = new URL("http://localhost:8000/api/login");
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                String jsonInputString = String.format("{\"email\": \"%s\", \"password\": \"%s\"}", email, password);

                try (OutputStream os = conn.getOutputStream()) {
                    byte[] input = jsonInputString.getBytes(StandardCharsets.UTF_8);
                    os.write(input, 0, input.length);
                }

                int code = conn.getResponseCode();
                if (code == 200) {
                    Scanner s = new Scanner(conn.getInputStream()).useDelimiter("\\A");
                    String result = s.hasNext() ? s.next() : "";
                    
                    // Simple JSON parsing (expecting {"token":"...", "userId":1})
                    String token = extractJsonValue(result, "token");
                    String userIdStr = extractJsonValue(result, "userId");
                    int userId = userIdStr.isEmpty() ? 1 : Integer.parseInt(userIdStr);

                    SessionManager.token = token;
                    SessionManager.userId = userId;
                    SessionManager.userEmail = email;

                    Platform.runLater(this::loadDashboard);
                } else {
                    Platform.runLater(() -> {
                        errorLabel.setText("Invalid credentials.");
                        errorLabel.setVisible(true);
                    });
                }
            } catch (Exception e) {
                Platform.runLater(() -> {
                    errorLabel.setText("Server error: " + e.getMessage());
                    errorLabel.setVisible(true);
                });
            }
        }).start();
    }

    private String extractJsonValue(String json, String key) {
        String pattern = "\"" + key + "\":\"?([^,\"}]+)\"?";
        java.util.regex.Matcher matcher = java.util.regex.Pattern.compile(pattern).matcher(json);
        if (matcher.find()) {
            return matcher.group(1);
        }
        return "";
    }

    @FXML
    public void onRegister() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("register-view.fxml"));
            Scene scene = new Scene(loader.load(), 900, 700);
            RegisterController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) emailField.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Register");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    public void onTogglePassword() {
        if (passwordField.isVisible()) {
            passwordVisible.setText(passwordField.getText());
            passwordVisible.setVisible(true);
            passwordField.setVisible(false);
            showPasswordBtn.setText("🙈");
        } else {
            passwordField.setText(passwordVisible.getText());
            passwordField.setVisible(true);
            passwordVisible.setVisible(false);
            showPasswordBtn.setText("👁");
        }
    }

    @FXML
    public void onForgotPassword() {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Forgot Password");
        alert.setHeaderText(null);
        alert.setContentText("Please use the web app to reset your password.");
        alert.showAndWait();
    }

    private void loadDashboard() {
        try {
            // Re-initialize services with the logged-in user ID
            dbManager.ensureDeviceState(SessionManager.userId);
            
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) emailField.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Dashboard");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
