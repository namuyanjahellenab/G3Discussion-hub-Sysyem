package com.discussionhub.client.utils;

import com.discussionhub.client.SessionManager;
import javafx.scene.Scene;
import javafx.stage.Stage;

import java.net.URL;

// Every screen switch replaces the Stage's Scene, but the Stage itself
// persists across the switch. Applying a new Scene here without reading
// the Stage's current width/height first would otherwise implicitly snap
// the window to whatever size the new Scene's root prefers, so instead
// this always carries the exact width/height/position of whichever screen
// was open a moment ago forward onto the new one - the new screen takes
// the size of its parent screen, no exceptions, no maximized-state special
// casing (that used to re-derive a size from the OS "maximized" flag on
// every switch, which is what was producing a different effective size
// per screen instead of a genuinely fixed one).
public final class WindowUtil {

    private WindowUtil() {
    }

    public static void applyScene(Stage stage, Scene scene, String title) {
        double width = stage.getWidth();
        double height = stage.getHeight();
        double x = stage.getX();
        double y = stage.getY();

        stage.setScene(scene);
        stage.setTitle(title);
        stage.setMaximized(false);
        applyTheme(scene);

        if (width > 0 && height > 0) {
            stage.setWidth(width);
            stage.setHeight(height);
        }
        if (!Double.isNaN(x) && !Double.isNaN(y)) {
            stage.setX(x);
            stage.setY(y);
        }
    }

    /**
     * Layers the user's saved accent-color theme (SessionManager.currentTheme
     * - "luna"/"black"/"brown"/"green", matching User.ThemeColor on the web
     * side exactly) on top of whichever stylesheets the Scene's FXML already
     * declared (app-theme.css). Public and separate from applyScene() so
     * SettingsController can re-apply immediately after a theme change is
     * saved, without needing to reload the current screen. A no-op for
     * "luna" - that's app-theme.css's own default palette, nothing to
     * override. Always clears any previously-applied theme override first,
     * so switching themes twice in a row can't stack stale colors.
     */
    public static void applyTheme(Scene scene) {
        if (scene == null || scene.getRoot() == null) {
            return;
        }
        // app-theme.css is declared on each screen's root node (FXML
        // stylesheets="@app-theme.css"), not on the Scene - and a Parent's
        // own stylesheets always outrank Scene-level stylesheets in
        // JavaFX's cascade regardless of add order. Adding the override to
        // scene.getStylesheets() therefore never actually beat app-theme.css.
        // Adding it to the root node's own list instead, after app-theme.css,
        // makes it win by list order within the same origin.
        var rootStylesheets = scene.getRoot().getStylesheets();
        rootStylesheets.removeIf(sheet -> sheet.contains("/theme-") && sheet.endsWith(".css"));

        String theme = SessionManager.currentTheme;
        if (theme == null || theme.equals("luna")) {
            return;
        }

        URL themeCss = WindowUtil.class.getResource("/com/discussionhub/client/theme-" + theme + ".css");
        if (themeCss != null) {
            rootStylesheets.add(themeCss.toExternalForm());
        }
    }
}
