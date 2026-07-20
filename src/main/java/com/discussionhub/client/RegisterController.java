package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

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
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class RegisterController {

    @FXML private TextField fullNameField;
    @FXML private TextField studentIdField;
    @FXML private TextField emailField;
    @FXML private TextField usernameField;
    @FXML private PasswordField passwordField;
    @FXML private TextField passwordTextField;
    @FXML private Button showPasswordBtn;
    @FXML private PasswordField confirmPasswordField;
    @FXML private TextField confirmPasswordTextField;
    @FXML private Button showConfirmPasswordBtn;
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
        passwordField.textProperty().addListener((obs, oldVal, newVal) -> validatePassword(newVal));

        // PasswordField has no "reveal plaintext" mode of its own (unlike a
        // web <input type="password"> flipping to type="text") - keeping a
        // same-spot TextField bound to the same text, shown only when
        // revealed, is the standard JavaFX workaround. Bidirectional so
        // typing into whichever one is visible keeps the other in sync.
        passwordTextField.textProperty().bindBidirectional(passwordField.textProperty());
        confirmPasswordTextField.textProperty().bindBidirectional(confirmPasswordField.textProperty());
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

    // The checkbox is the sole gate on registering now - no separate
    // "accept" button to click through, matching the web.
    @FXML
    public void onRulesChecked() {
        boolean accepted = rulesCheckBox.isSelected();
        completeRegistrationButton.setDisable(!accepted);
        statusBar.setText(accepted ? "✓ RULES ACCEPTED" : "✓ STATUS: PENDING RULE ACCEPTANCE");
    }

    /** The rules text sits next to the checkbox as an independent sibling
     *  (not the checkbox's own text/graphic - see register-view.fxml for
     *  why), so clicking it doesn't toggle the box automatically the way
     *  clicking a real Labeled's text would. This wires that up by hand,
     *  matching the web's <label for="rules_accepted"> behavior. */
    @FXML
    public void onRulesTextClicked() {
        rulesCheckBox.setSelected(!rulesCheckBox.isSelected());
        onRulesChecked();
    }

    @FXML
    public void onCompleteRegistration() {
        String name = fullNameField.getText();
        String studentId = studentIdField.getText();
        String email = emailField.getText();
        String username = usernameField.getText();
        String password = passwordField.getText();
        String confirm = confirmPasswordField.getText();

        if (name.isEmpty() || studentId.isEmpty() || email.isEmpty() || username.isEmpty() || password.isEmpty()) {
            showError("All fields are required.");
            return;
        }

        if (!password.equals(confirm)) {
            showError("Passwords do not match.");
            return;
        }

        if (!rulesCheckBox.isSelected()) {
            showError("You must accept the platform rules to proceed.");
            return;
        }

        new Thread(() -> {
            try {
                URL url = URI.create("http://localhost:8000/api/register").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);

                org.json.JSONObject payload = new org.json.JSONObject();
                payload.put("full_name", name);
                payload.put("student_id_number", studentId);
                payload.put("email", email);
                payload.put("username", username);
                payload.put("password", password);
                payload.put("password_confirmation", confirm);
                payload.put("rules_accepted", true);

                try (OutputStream os = conn.getOutputStream()) {
                    os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
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
                    String errorBody = readErrorBody(conn);
                    String message = extractFirstError(errorBody);
                    Platform.runLater(() -> showError(message));
                }
            } catch (Exception e) {
                Platform.runLater(() -> showError("Network error: " + e.getMessage()));
            }
        }).start();
    }

    private String readErrorBody(HttpURLConnection conn) {
        try (java.io.BufferedReader in = new java.io.BufferedReader(
                new java.io.InputStreamReader(conn.getErrorStream(), StandardCharsets.UTF_8))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = in.readLine()) != null) sb.append(line);
            return sb.toString();
        } catch (Exception e) {
            return "";
        }
    }

    /** Laravel validation errors come back as {"errors": {"field": ["message"]}}. */
    private String extractFirstError(String body) {
        try {
            org.json.JSONObject json = new org.json.JSONObject(body);
            if (json.has("errors")) {
                org.json.JSONObject errors = json.getJSONObject("errors");
                String firstKey = errors.keys().next();
                return errors.getJSONArray(firstKey).getString(0);
            }
            return json.optString("message", "Registration failed.");
        } catch (Exception e) {
            return "Registration failed.";
        }
    }

    @FXML
    public void onSignIn() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("login-view.fxml"));
            Scene scene = new Scene(loader.load());
            LoginController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) fullNameField.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Login");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    public void onTogglePassword() {
        boolean nowRevealed = !passwordTextField.isVisible();
        passwordTextField.setVisible(nowRevealed);
        passwordTextField.setManaged(nowRevealed);
        passwordField.setVisible(!nowRevealed);
        passwordField.setManaged(!nowRevealed);
        // Single-codepoint emoji only - the multi-codepoint "eye in speech
        // bubble" ZWJ sequence isn't in JavaFX's font, rendering as a tofu
        // box next to a correctly-drawn eye instead of one combined glyph.
        showPasswordBtn.setText(nowRevealed ? "🙈" : "👁");
    }

    @FXML
    public void onToggleConfirmPassword() {
        boolean nowRevealed = !confirmPasswordTextField.isVisible();
        confirmPasswordTextField.setVisible(nowRevealed);
        confirmPasswordTextField.setManaged(nowRevealed);
        confirmPasswordField.setVisible(!nowRevealed);
        confirmPasswordField.setManaged(!nowRevealed);
        showConfirmPasswordBtn.setText(nowRevealed ? "🙈" : "👁");
    }

    private void showError(String msg) {
        errorLabel.setText(msg);
        errorLabel.setVisible(true);
    }
}
