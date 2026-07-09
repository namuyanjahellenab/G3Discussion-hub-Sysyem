package com.discussionhub.client.utils;

import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

/**
 * Switches the Stage's Scene while preserving whatever size/maximized state
 * the window already had — so navigating between screens never resets a
 * user's resized or maximized window back to some hardcoded default size.
 */
public class NavigationUtil {

    public static void switchScene(Stage stage, Parent newRoot) {
        boolean wasMaximized = stage.isMaximized();
        double width = stage.getWidth();
        double height = stage.getHeight();

        Scene scene = new Scene(newRoot);
        stage.setScene(scene);

        if (wasMaximized) {
            stage.setMaximized(true);
        } else if (width > 0 && height > 0) {
            stage.setWidth(width);
            stage.setHeight(height);
        }
    }
}
