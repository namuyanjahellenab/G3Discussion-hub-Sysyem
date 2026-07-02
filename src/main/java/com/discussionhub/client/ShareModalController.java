package com.discussionhub.client;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.input.Clipboard;
import javafx.scene.input.ClipboardContent;
import javafx.stage.Stage;

import java.awt.Desktop;
import java.net.URI;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

public class ShareModalController {

    @FXML private Label postPreviewLabel;
    @FXML private ToggleButton twitterBtn, facebookBtn, linkedinBtn;
    @FXML private Button copyLinkBtn;
    @FXML private TextArea optionalMessageArea;

    private String postContent;
    private String postUrl;

    public void setPostContent(String content, String postUrl) {
        this.postContent = content;
        this.postUrl = postUrl;
        
        String preview = content;
        if (preview.length() > 150) {
            preview = preview.substring(0, 147) + "...";
        }
        postPreviewLabel.setText(preview);
        
        setupToggleStyle(twitterBtn);
        setupToggleStyle(facebookBtn);
        setupToggleStyle(linkedinBtn);
    }

    private void setupToggleStyle(ToggleButton btn) {
        btn.selectedProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal) {
                btn.setStyle("-fx-background-color: #1a73e8; -fx-text-fill: white; -fx-border-color: #1a73e8; -fx-border-radius: 4; -fx-padding: 8 12;");
            } else {
                btn.setStyle("-fx-background-color: white; -fx-text-fill: black; -fx-border-color: #ddd; -fx-border-radius: 4; -fx-padding: 8 12;");
            }
        });
    }

    @FXML
    public void onCopyLink() {
        Clipboard clipboard = Clipboard.getSystemClipboard();
        ClipboardContent content = new ClipboardContent();
        content.putString(postUrl);
        clipboard.setContent(content);
        
        String oldText = copyLinkBtn.getText();
        copyLinkBtn.setText("Copied!");
        new Thread(() -> {
            try { Thread.sleep(2000); } catch (InterruptedException e) {}
            javafx.application.Platform.runLater(() -> copyLinkBtn.setText(oldText));
        }).start();
    }

    @FXML
    public void onShareNow() {
        String msg = optionalMessageArea.getText();
        try {
            if (twitterBtn.isSelected()) {
                browse("https://twitter.com/intent/tweet?text=" + encode(msg) + "&url=" + encode(postUrl));
            }
            if (facebookBtn.isSelected()) {
                browse("https://www.facebook.com/sharer/sharer.php?u=" + encode(postUrl));
            }
            if (linkedinBtn.isSelected()) {
                browse("https://www.linkedin.com/sharing/share-offsite/?url=" + encode(postUrl));
            }
            onCancel();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    public void onCancel() {
        Stage stage = (Stage) postPreviewLabel.getScene().getWindow();
        stage.close();
    }

    private void browse(String url) throws Exception {
        if (Desktop.isDesktopSupported() && Desktop.getDesktop().isSupported(Desktop.Action.BROWSE)) {
            Desktop.getDesktop().browse(new URI(url));
        }
    }

    private String encode(String val) {
        try {
            return URLEncoder.encode(val, StandardCharsets.UTF_8.toString());
        } catch (Exception e) {
            return val;
        }
    }
}
