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

    // Whether the sidebar is collapsed to its icon-only rail. Lives here
    // (not as a SidebarController field) because a new SidebarController is
    // constructed on every screen navigation (fresh fx:include each time) -
    // an instance field would silently reset to expanded on every click of
    // any nav button, which isn't what "toggle" should mean.
    public static boolean sidebarCollapsed = false;
}
