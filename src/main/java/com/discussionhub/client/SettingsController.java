package com.discussionhub.client;

import com.discussionhub.client.utils.WindowUtil;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.TextUtil;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.control.ToggleButton;
import javafx.scene.control.ToggleGroup;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashMap;
import java.util.Map;

// Mirrors resources/views/settings/index.blade.php: Profile, Password,
// Appearance (theme swatches), and Sessions (logout / logout everywhere).
public class SettingsController {

    private static final String BASE_URL = "http://127.0.0.1:8000";
    private static final Map<String, String> THEMES = new LinkedHashMap<>();
    static {
        THEMES.put("luna", "#26658C");
        THEMES.put("black", "#2B2B2B");
        THEMES.put("brown", "#8C5A2B");
        THEMES.put("green", "#2F7D5E");
    }

    @FXML private TextField nameField;
    @FXML private TextField emailField;
    @FXML private PasswordField currentPasswordField;
    @FXML private PasswordField newPasswordField;
    @FXML private PasswordField confirmPasswordField;
    // Plain-text twins shown in place of the masked field while its "eye"
    // toggle is on - JavaFX's PasswordField has no built-in reveal option,
    // so this is the standard workaround: two stacked controls sharing one
    // bound text value, with only one visible at a time.
    @FXML private TextField currentPasswordVisibleField;
    @FXML private TextField newPasswordVisibleField;
    @FXML private TextField confirmPasswordVisibleField;
    @FXML private Button currentPasswordToggle;
    @FXML private Button newPasswordToggle;
    @FXML private Button confirmPasswordToggle;
    @FXML private Label statusLabel;
    @FXML private Label errorLabel;
    @FXML private Label userInitialsLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private HBox themeSwatchesBox;
    @FXML private SidebarController sidebarController;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;
    private String selectedTheme = SessionManager.currentTheme;

    // Called automatically by FXMLLoader once @FXML fields are injected -
    // binding here (rather than in setServices()) keeps the reveal-toggle
    // wiring independent of the async services/session setup below it.
    @FXML
    private void initialize() {
        currentPasswordVisibleField.textProperty().bindBidirectional(currentPasswordField.textProperty());
        newPasswordVisibleField.textProperty().bindBidirectional(newPasswordField.textProperty());
        confirmPasswordVisibleField.textProperty().bindBidirectional(confirmPasswordField.textProperty());
    }

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        sidebarController.setServices(dbManager, syncService);
        sidebarController.setActive("settings");

        nameField.setText(SessionManager.fullName);
        emailField.setText(SessionManager.userEmail);

        String name = (SessionManager.fullName == null || SessionManager.fullName.isBlank())
            ? SessionManager.userEmail : SessionManager.fullName;
        userNameLabel.setText(name);
        userInitialsLabel.setText(TextUtil.initials(name));
        userRoleLabel.setText((SessionManager.role == null || SessionManager.role.isBlank() ? "Student" : SessionManager.role) + " Account");

        buildThemeSwatches();
    }

    private void buildThemeSwatches() {
        themeSwatchesBox.getChildren().clear();
        ToggleGroup group = new ToggleGroup();
        for (Map.Entry<String, String> theme : THEMES.entrySet()) {
            VBox option = new VBox(6);
            option.setAlignment(Pos.CENTER);

            ToggleButton swatch = new ToggleButton();
            swatch.setToggleGroup(group);
            swatch.setSelected(theme.getKey().equals(selectedTheme));
            swatch.setStyle("-fx-background-color: " + theme.getValue() + "; -fx-background-radius: 22; " +
                "-fx-min-width: 44; -fx-min-height: 44; -fx-max-width: 44; -fx-max-height: 44; -fx-text-fill: white;");
            swatch.textProperty().bind(javafx.beans.binding.Bindings.when(swatch.selectedProperty()).then("✔").otherwise(""));
            swatch.setOnAction(e -> selectedTheme = theme.getKey());

            Label label = new Label(themeLabel(theme.getKey()));
            label.getStyleClass().add("muted-text");
            label.setStyle("-fx-font-weight: bold; -fx-font-size: 11;");

            option.getChildren().addAll(swatch, label);
            themeSwatchesBox.getChildren().add(option);
        }
    }

    private String themeLabel(String key) {
        return switch (key) {
            case "luna" -> "Blue-Teal (Default)";
            case "black" -> "Black";
            case "brown" -> "Brown";
            case "green" -> "Green";
            default -> key;
        };
    }

    @FXML
    protected void onToggleCurrentPasswordVisibility() {
        togglePasswordVisibility(currentPasswordField, currentPasswordVisibleField, currentPasswordToggle);
    }

    @FXML
    protected void onToggleNewPasswordVisibility() {
        togglePasswordVisibility(newPasswordField, newPasswordVisibleField, newPasswordToggle);
    }

    @FXML
    protected void onToggleConfirmPasswordVisibility() {
        togglePasswordVisibility(confirmPasswordField, confirmPasswordVisibleField, confirmPasswordToggle);
    }

    private void togglePasswordVisibility(PasswordField masked, TextField plain, Button toggle) {
        boolean nowPlain = !plain.isVisible();
        masked.setVisible(!nowPlain);
        masked.setManaged(!nowPlain);
        plain.setVisible(nowPlain);
        plain.setManaged(nowPlain);
        toggle.setText(nowPlain ? "🙈" : "👁");
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
                URL url = URI.create(BASE_URL + "/api/settings").toURL();
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("PUT");
                conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("Accept", "application/json");
                conn.setDoOutput(true);

                JSONObject payload = new JSONObject();
                payload.put("name", name);
                payload.put("email", email);
                payload.put("theme_color", selectedTheme);
                if (!newPassword.isEmpty()) {
                    payload.put("current_password", currentPassword);
                    payload.put("new_password", newPassword);
                }

                try (OutputStream os = conn.getOutputStream()) {
                    os.write(payload.toString().getBytes(StandardCharsets.UTF_8));
                }

                int code = conn.getResponseCode();
                if (code == 200) {
                    SessionManager.fullName = name;
                    SessionManager.userEmail = email;
                    SessionManager.currentTheme = selectedTheme;
                    Platform.runLater(() -> {
                        WindowUtil.applyTheme(nameField.getScene());
                        showStatus("Settings updated successfully.");
                    });
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
            post("/api/logout");
            Platform.runLater(this::signOutLocally);
        }).start();
    }

    @FXML
    protected void onLogoutAllDevices() {
        javafx.scene.control.Alert confirm = new javafx.scene.control.Alert(
            javafx.scene.control.Alert.AlertType.CONFIRMATION,
            "This will log you out on every device where you're signed in. Continue?");
        confirm.showAndWait().ifPresent(btn -> {
            if (btn == javafx.scene.control.ButtonType.OK) {
                new Thread(() -> {
                    post("/api/logout-all-devices");
                    Platform.runLater(this::signOutLocally);
                }).start();
            }
        });
    }

    private void post(String path) {
        try {
            URL url = URI.create(BASE_URL + path).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Authorization", "Bearer " + SessionManager.token);
            conn.setRequestProperty("Accept", "application/json");
            conn.getResponseCode();
        } catch (Exception e) {
            System.err.println("[Settings] Request to " + path + " failed (proceeding locally anyway): " + e.getMessage());
        }
    }

    private void signOutLocally() {
        sidebarController.stopNotificationPolling();
        SessionManager.token = "";
        SessionManager.userId = 0;
        SessionManager.userEmail = "";
        SessionManager.fullName = "";
        SessionManager.role = "";
        SessionManager.currentTheme = "luna";
        SessionManager.sidebarCollapsed = false;
        loadLogin();
    }

    private void loadLogin() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("login-view.fxml"));
            Scene scene = new Scene(loader.load());
            LoginController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) nameField.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Campus Login");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    protected void onBack() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("dashboard-view.fxml"));
            Scene scene = new Scene(loader.load());
            DashboardController controller = loader.getController();
            controller.setServices(dbManager, syncService);

            Stage stage = (Stage) nameField.getScene().getWindow();
            WindowUtil.applyScene(stage, scene, "DiscussionHub — Dashboard");
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
}
