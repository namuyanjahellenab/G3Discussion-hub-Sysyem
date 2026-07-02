package com.discussionhub.client.database;

public class NotificationItem {
    private int notificationId;
    private int userId;
    private String message;
    private int status;
    private String createdAt;
    private String type;

    public NotificationItem(int notificationId, int userId, String message, int status, String createdAt, String type) {
        this.notificationId = notificationId;
        this.userId = userId;
        this.message = message;
        this.status = status;
        this.createdAt = createdAt;
        this.type = type;
    }

    public int getNotificationId() { return notificationId; }
    public int getUserId() { return userId; }
    public String getMessage() { return message; }
    public int getStatus() { return status; }
    public String getCreatedAt() { return createdAt; }
    public String getType() { return type; }
}
