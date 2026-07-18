package com.discussionhub.client.database;

public class CachedReplyItem {
    private final int replyId;
    private final int postId;
    private final int userId;
    private final String content;
    private final String createdAt;
    private final boolean pending;
    private final String authorName;
    private final String attachmentUrl;
    private final String attachmentType;
    private final String attachmentName;

    public CachedReplyItem(int replyId, int postId, int userId, String content, String createdAt, boolean pending, String authorName,
                            String attachmentUrl, String attachmentType, String attachmentName) {
        this.replyId = replyId;
        this.postId = postId;
        this.userId = userId;
        this.content = content;
        this.createdAt = createdAt;
        this.pending = pending;
        this.authorName = authorName;
        this.attachmentUrl = attachmentUrl;
        this.attachmentType = attachmentType;
        this.attachmentName = attachmentName;
    }

    public int getReplyId() { return replyId; }
    public int getPostId() { return postId; }
    public int getUserId() { return userId; }
    public String getContent() { return content; }
    public String getCreatedAt() { return createdAt; }
    public boolean isPending() { return pending; }
    public String getAuthorName() { return authorName; }
    public String getAttachmentUrl() { return attachmentUrl; }
    public String getAttachmentType() { return attachmentType; }
    public String getAttachmentName() { return attachmentName; }
}
