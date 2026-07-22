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
import java.io.InputStream;
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
                        String role = extractJsonValue(result, "role");
                        String themeColor = extractJsonValue(result, "theme_color");

                        SessionManager.token = token;
                        SessionManager.userId = userId;
                        SessionManager.userEmail = email;
                        SessionManager.fullName = name;
                        SessionManager.role = role;
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

            QuizPopupService.start(stage, SessionManager.token, dbManager, syncService);
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

            QuizPopupService.start(stage, SessionManager.token, dbManager, syncService);
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

    /**
     * Sends the same reset-link email the web's "Forgot Password?" form
     * does (POST /api/forgot-password -> AuthController::apiForgotPassword,
     * which just calls Password::sendResetLink()) - the desktop has no way
     * to render the "choose a new password" form itself (no browser tab to
     * put it in), so the emailed link is still what the student clicks to
     * actually finish resetting it, same as the web flow.
     */
    @FXML
    public void onForgotPassword() {
        TextInputDialog dialog = new TextInputDialog(emailField.getText());
        dialog.setTitle("Forgot Password");
        dialog.setHeaderText("Enter your account email");
        dialog.setContentText("Email:");
        dialog.showAndWait().ifPresent(email -> {
            String trimmed = email.trim();
            if (trimmed.isEmpty()) return;
            sendForgotPasswordRequest(trimmed);
        });
    }

    private void sendForgotPasswordRequest(String email) {
        new Thread(() -> {
            try {
                URL url = URI.create("http://localhost:8000/api/forgot-password").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);

                String payload = "{\"email\": \"" + email.replace("\"", "\\\"") + "\"}";
                try (OutputStream os = conn.getOutputStream()) {
                    os.write(payload.getBytes(StandardCharsets.UTF_8));
                }

                int code = conn.getResponseCode();
                InputStream stream = code == 200 ? conn.getInputStream() : conn.getErrorStream();
                String body;
                try (Scanner s = new Scanner(stream, StandardCharsets.UTF_8).useDelimiter("\\A")) {
                    body = s.hasNext() ? s.next() : "";
                }
                String message = extractJsonValue(body, "message");

                Platform.runLater(() -> {
                    Alert alert = new Alert(code == 200 ? Alert.AlertType.INFORMATION : Alert.AlertType.WARNING);
                    alert.setTitle("Forgot Password");
                    alert.setHeaderText(null);
                    alert.setContentText(code == 200
                        ? "If that email is registered, a reset link has been sent. Open it in your browser to choose a new password."
                        : (message.isEmpty() ? "Could not send the reset link. Check the email and try again." : message));
                    alert.showAndWait();
                });
            } catch (Exception e) {
                Platform.runLater(() -> {
                    Alert alert = new Alert(Alert.AlertType.ERROR, "Couldn't reach the server — check your connection.");
                    alert.setHeaderText(null);
                    alert.showAndWait();
                });
            }
        }).start();
    }

}
