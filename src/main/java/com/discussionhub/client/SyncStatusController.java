package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.DeltaSyncService;
import com.discussionhub.client.utils.NetworkUtil;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.paint.Color;
import javafx.scene.shape.Circle;

public class SyncStatusController {

    @FXML private Circle statusCircle;
    @FXML private Label networkStateLabel;
    @FXML private Label pendingCountLabel;
    @FXML private Label lastSyncLabel;
    @FXML private Button syncNowButton;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
        refreshMetrics();
    }

    @FXML
    public void onRefreshMetricsClick() {
        refreshMetrics();
    }

    @FXML
    public void onSyncNowClick() {
        if (syncService != null) {
            syncService.synchronizeLocalChanges();
            refreshMetrics();
        }
    }

    private void refreshMetrics() {
        if (dbManager == null) return;

        // 1. Check network state
        boolean isOnline = NetworkUtil.isNetworkAvailable();
        networkStateLabel.setText(isOnline ? "Online" : "Offline (Local mode)");
        statusCircle.setFill(isOnline ? Color.GREEN : Color.GRAY);
        syncNowButton.setDisable(!isOnline);

        // 2. Pending sync items count
        int pendingItems = dbManager.getPendingChanges().size();
        pendingCountLabel.setText(pendingItems + " items pending");

        // 3. Last successful sync
        String lastSync = dbManager.getLastSyncTimestamp();
        lastSyncLabel.setText(lastSync != null ? lastSync : "Never synced");
    }
}