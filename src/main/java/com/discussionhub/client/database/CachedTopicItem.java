package com.discussionhub.client.database;

public class CachedTopicItem {
    private final int topicId;
    private final String title;
    private final String category;
    private final int createdBy;
    private final String createdAt;
    private final boolean pending;
    private final int groupId;

    public CachedTopicItem(int topicId, String title, String category, int createdBy, String createdAt, boolean pending, int groupId) {
        this.topicId = topicId;
        this.title = title;
        this.category = category;
        this.createdBy = createdBy;
        this.createdAt = createdAt;
        this.pending = pending;
        this.groupId = groupId;
    }

    public int getTopicId() { return topicId; }
    public String getTitle() { return title; }
    public String getCategory() { return category; }
    public int getCreatedBy() { return createdBy; }
    public String getCreatedAt() { return createdAt; }
    public boolean isPending() { return pending; }
    public int getGroupId() { return groupId; }
}
