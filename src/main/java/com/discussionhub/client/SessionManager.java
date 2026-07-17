package com.discussionhub.client;

public class SessionManager {
    public static String token = "";
    public static int userId = 1;
    public static String userEmail = "";
    public static String fullName = "";

    // "luna" (default), "black", "brown", or "green" - matches User.ThemeColor
    // on the web side exactly. WindowUtil.applyScene() reads this on every
    // screen switch to layer the matching theme-*.css override on top of
    // app-theme.css, so desktop stays visually in sync with whatever the
    // user picked in Settings (on either platform).
    public static String currentTheme = "luna";
}
