package com.discussionhub.client.model;

public class TopicItem {

    private final int topicId;
    private final String title;
    private final String category;
    private final int createdBy;
    private final String createdAt;
    private final int replyCount;
    private final int groupId; // 0 means "no group assigned yet"

    public TopicItem(int topicId, String title, String category,
                     int createdBy, String createdAt, int replyCount, int groupId) {
        this.topicId = topicId;
        this.title = title;
        this.category = category;
        this.createdBy = createdBy;
        this.createdAt = createdAt;
        this.replyCount = replyCount;
        this.groupId = groupId;
    }

    public int getTopicId()     { return topicId; }
    public String getTitle()    { return title; }
    public String getCategory() { return category; }
    public int getCreatedBy()   { return createdBy; }
    public String getCreatedAt(){ return createdAt; }
    public int getReplyCount()  { return replyCount; }
    public int getGroupId()     { return groupId; }

    @Override
    public String toString() { return title; }
}
