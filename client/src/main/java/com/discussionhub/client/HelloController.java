package com.discussionhub.client;

import com.discussionhub.client.database.DatabaseManager;
import com.discussionhub.client.utils.NetworkUtil;
import com.discussionhub.client.utils.DeltaSyncService;
import javafx.fxml.FXML;
import javafx.scene.control.Label;

public class HelloController {

    @FXML
    private Label welcomeText;

    private DatabaseManager dbManager;
    private DeltaSyncService syncService;

    /**
     * Called by HelloApplication right after this controller is loaded, so it
     * shares the same DatabaseManager/DeltaSyncService the rest of the app uses,
     * instead of creating its own disconnected pair.
     */
    public void setServices(DatabaseManager dbManager, DeltaSyncService syncService) {
        this.dbManager = dbManager;
        this.syncService = syncService;
    }

    @FXML
    protected void onHelloButtonClick() {
        boolean isOnline = NetworkUtil.isNetworkAvailable();

        if (isOnline) {
            welcomeText.setText("Status: ONLINE! Syncing...");
            syncService.synchronizeLocalChanges();
            welcomeText.setText("Status: ONLINE. Sync complete!");
        } else {
            welcomeText.setText("Status: OFFLINE. Changes cached locally.");
        }
    }
}