package com.discussionhub.client.database;

public class CachedPostItem {
    private final int postId;
    private final int topicId;
    private final int userId;
    private final String content;
    private final String createdAt;
    private final boolean pending;
    private final String authorName;

    public CachedPostItem(int postId, int topicId, int userId, String content, String createdAt, boolean pending, String authorName) {
        this.postId = postId;
        this.topicId = topicId;
        this.userId = userId;
        this.content = content;
        this.createdAt = createdAt;
        this.pending = pending;
        this.authorName = authorName;
    }

    public int getPostId() { return postId; }
    public int getTopicId() { return topicId; }
    public int getUserId() { return userId; }
    public String getContent() { return content; }
    public String getCreatedAt() { return createdAt; }
    public boolean isPending() { return pending; }
    public String getAuthorName() { return authorName; }
}
