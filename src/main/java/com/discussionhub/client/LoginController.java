package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.quiz.QuizPopupService;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.stage.Stage;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
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
                URL url = URI.create("http://localhost:8000/api/login").toURL();
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
                    try (Scanner s = new Scanner(conn.getInputStream()).useDelimiter("\\A")) {
                        String result = s.hasNext() ? s.next() : "";

                        String token = extractJsonValue(result, "token");
                        String userIdStr = extractJsonValue(result, "id");
                        int userId = userIdStr.isEmpty() ? 1 : Integer.parseInt(userIdStr);
                        String name = extractJsonValue(result, "name");
                        String themeColor = extractJsonValue(result, "theme_color");

                        SessionManager.token = token;
                        SessionManager.userId = userId;
                        SessionManager.userEmail = email;
                        SessionManager.fullName = name;
                        SessionManager.currentTheme = themeColor.isEmpty() ? "luna" : themeColor;

                        dbManager.ensureDeviceState(SessionManager.userId);

                        checkGroupsAndNavigate();
                    }
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

    /**
     * Decides where to send the student right after login:
     * - Belongs to at least one group already -> straight to Dashboard.
     * - Belongs to zero groups (brand-new account) -> Group Selection screen first.
     * Runs on a background thread since it's a network call; navigation itself
     * happens back on the JavaFX thread via Platform.runLater().
     */
    private void checkGroupsAndNavigate() {
        boolean hasAtLeastOneGroup = false;
        try {
            URL url = URI.create("http://localhost:8000/api/groups").toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");

            if (conn.getResponseCode() == 200) {
                BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder response = new StringBuilder();
                String line;
                while ((line = in.readLine()) != null) response.append(line);
                in.close();

                hasAtLeastOneGroup = response.toString().contains("\"is_member\":true");
            }
        } catch (Exception e) {
            System.err.println("[Login] Error checking group membership: " + e.getMessage());
        }

        boolean finalHasGroup = hasAtLeastOneGroup;
        Platform.runLater(() -> {
            if (finalHasGroup) {
                loadDashboard();
            } else {
                loadGroupSelection();
            }
        });
    }

    private void loadDashboard() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) emailField.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Dashboard");

            QuizPopupService.start(stage, SessionManager.token);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void loadGroupSelection() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("group-selection-view.fxml"));
            Scene scene = new Scene(loader.load());
            GroupSelectionController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) emailField.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Select Group");

            QuizPopupService.start(stage, SessionManager.token);
        } catch (Exception e) {
            e.printStackTrace();
        }
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
            Scene scene = new Scene(loader.load());
            RegisterController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) emailField.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Register");
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

}
