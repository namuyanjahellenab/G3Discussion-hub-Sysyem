package com.discussionhub.client.model;

/** One row from the local SyncQueue table. */
public class SyncQueueItem {

    private final int syncQueueId;
    private final int deviceId;
    private final String entityType;
    private final long entityId;
    private final String operation;
    private final String payload;

    public SyncQueueItem(int syncQueueId, int deviceId, String entityType,
                         long entityId, String operation, String payload) {
        this.syncQueueId = syncQueueId;
        this.deviceId = deviceId;
        this.entityType = entityType;
        this.entityId = entityId;
        this.operation = operation;
        this.payload = payload;
    }

    public int getSyncQueueId() { return syncQueueId; }
    public int getDeviceId() { return deviceId; }
    public String getEntityType() { return entityType; }
    public long getEntityId() { return entityId; }
    public String getOperation() { return operation; }
    public String getPayload() { return payload; }
}