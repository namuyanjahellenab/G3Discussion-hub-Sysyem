package com.discussionhub.client.database;

public class ChatMessageItem {
    private int messageId;
    private int conversationId;
    private int userId;
    private String authorName;
    private String body;
    private String createdAt;
    private boolean pending;

    public ChatMessageItem(int messageId, int conversationId, int userId, String authorName,
                            String body, String createdAt, boolean pending) {
        this.messageId = messageId;
        this.conversationId = conversationId;
        this.userId = userId;
        this.authorName = authorName;
        this.body = body;
        this.createdAt = createdAt;
        this.pending = pending;
    }

    public int getUserId() { return userId; }
    public String getAuthorName() { return authorName; }
    public String getBody() { return body; }
    public String getCreatedAt() { return createdAt; }
    public boolean isPending() { return pending; }
}
