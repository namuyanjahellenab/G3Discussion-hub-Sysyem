package com.discussionhub.client.database;

/** A "Remember Me" login persisted locally, see DatabaseManager.saveSession()/
 *  loadSession(). Lets HelloApplication skip the login screen on a cold
 *  start, including while offline, the same way WhatsApp stays signed in. */
public class SavedSession {
    private final String token;
    private final int userId;
    private final String userEmail;
    private final String fullName;
    private final String role;
    private final String themeColor;

    public SavedSession(String token, int userId, String userEmail, String fullName, String role, String themeColor) {
        this.token = token;
        this.userId = userId;
        this.userEmail = userEmail;
        this.fullName = fullName;
        this.role = role;
        this.themeColor = themeColor;
    }

    public String getToken() { return token; }
    public int getUserId() { return userId; }
    public String getUserEmail() { return userEmail; }
    public String getFullName() { return fullName; }
    public String getRole() { return role; }
    public String getThemeColor() { return themeColor; }
}
