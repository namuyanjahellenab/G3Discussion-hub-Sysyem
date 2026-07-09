package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.stage.Stage;
import javafx.scene.control.Button;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class RegisterController {

    @FXML private TextField fullNameField;
    @FXML private TextField emailField;
    @FXML private PasswordField passwordField;
    @FXML private TextField passwordVisibleField;
    @FXML private Button showPasswordBtn;
    @FXML private PasswordField confirmPasswordField;
    @FXML private Label lenLabel, upperLabel, lowerLabel, numLabel, specLabel;
    @FXML private Label statusBar;
    @FXML private Button completeRegistrationButton;
    @FXML private CheckBox rulesCheckBox;
    @FXML private Label errorLabel;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
    }

    @FXML
    public void initialize() {
        passwordVisibleField.textProperty().bindBidirectional(passwordField.textProperty());
        passwordField.textProperty().addListener((obs, oldVal, newVal) -> validatePassword(newVal));
    }

    private void validatePassword(String password) {
        boolean hasLen = password.length() >= 8;
        boolean hasUpper = password.matches(".*[A-Z].*");
        boolean hasLower = password.matches(".*[a-z].*");
        boolean hasNum = password.matches(".*\\d.*");
        boolean hasSpec = password.matches(".*[!@#$%^&*].*");

        updateLabel(lenLabel, hasLen);
        updateLabel(upperLabel, hasUpper);
        updateLabel(lowerLabel, hasLower);
        updateLabel(numLabel, hasNum);
        updateLabel(specLabel, hasSpec);
    }

    private void updateLabel(Label label, boolean valid) {
        if (valid) {
            label.setText("✅ " + label.getText().substring(2));
            label.setStyle("-fx-text-fill: green;");
        } else {
            label.setText("❌ " + label.getText().substring(2));
            label.setStyle("-fx-text-fill: black;");
        }
    }

    @FXML
    public void onRulesChecked() {
        boolean accepted = rulesCheckBox.isSelected();
        completeRegistrationButton.setDisable(!accepted);
        statusBar.setText(accepted ? "✓ Rules accepted" : "Please review and accept the rules to continue");
    }

    @FXML
    public void onDecline() {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Registration");
        alert.setHeaderText(null);
        alert.setContentText("Registration cancelled.");
        alert.showAndWait();
        onSignIn();
    }

    @FXML
    public void onCompleteRegistration() {
        String name = fullNameField.getText();
        String email = emailField.getText();
        String password = passwordField.getText();
        String confirm = confirmPasswordField.getText();

        if (name.isEmpty() || email.isEmpty() || password.isEmpty()) {
            showError("All fields are required.");
            return;
        }

        if (!password.equals(confirm)) {
            showError("Passwords do not match.");
            return;
        }

        new Thread(() -> {
            try {
                URL url = URI.create("http://localhost:8000/api/register").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);

                String json = String.format(
                    "{\"name\":\"%s\",\"email\":\"%s\",\"password\":\"%s\"}",
                    escapeJson(name), escapeJson(email), escapeJson(password)
                );

                try (OutputStream os = conn.getOutputStream()) {
                    os.write(json.getBytes(StandardCharsets.UTF_8));
                }

                int code = conn.getResponseCode();
                if (code == 200 || code == 201) {
                    Platform.runLater(() -> {
                        Alert alert = new Alert(Alert.AlertType.INFORMATION);
                        alert.setTitle("Success");
                        alert.setContentText("Registration successful! Please login.");
                        alert.showAndWait();
                        onSignIn();
                    });
                } else {
                    Platform.runLater(() -> showError("Registration failed (code " + code + "). Email may already be in use."));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showError("Network error: " + e.getMessage()));
            }
        }).start();
    }

    @FXML
    public void onSignIn() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("login-view.fxml"));
            Scene scene = new Scene(loader.load(), 800, 600);
            LoginController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) fullNameField.getScene().getWindow();
            stage.setScene(scene);
            stage.setTitle("DiscussionHub — Login");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    public void onTogglePassword() {
        boolean currentlyMasked = passwordField.isVisible();
        passwordField.setVisible(!currentlyMasked);
        passwordField.setManaged(!currentlyMasked);
        passwordVisibleField.setVisible(currentlyMasked);
        passwordVisibleField.setManaged(currentlyMasked);
        showPasswordBtn.setText(currentlyMasked ? "🙈" : "👁");
    }

    private void showError(String msg) {
        errorLabel.setText(msg);
        errorLabel.setVisible(true);
    }

    private String escapeJson(String input) {
        if (input == null) return "";
        return input.replace("\\", "\\\\").replace("\"", "\\\"");
    }
}
