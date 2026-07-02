package com.discussionhub.client.model;

public class TopicItem {

    private final int topicId;
    private final String title;
    private final String category;
    private final int createdBy;
    private final String createdAt;
    private final int replyCount;

    public TopicItem(int topicId, String title, String category,
                     int createdBy, String createdAt, int replyCount) {
        this.topicId = topicId;
        this.title = title;
        this.category = category;
        this.createdBy = createdBy;
        this.createdAt = createdAt;
        this.replyCount = replyCount;
    }

    public int getTopicId()     { return topicId; }
    public String getTitle()    { return title; }
    public String getCategory() { return category; }
    public int getCreatedBy()   { return createdBy; }
    public String getCreatedAt(){ return createdAt; }
    public int getReplyCount()  { return replyCount; }

    @Override
    public String toString() { return title; }
}