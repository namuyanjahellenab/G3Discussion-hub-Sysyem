package com.discussionhub.client.utils;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.nio.file.StandardCopyOption;
import java.security.MessageDigest;

// Local on-disk cache for reply/post attachment bytes (images + files),
// keyed by a hash of the full attachment URL. Lets an attachment already
// viewed once while online stay visible/openable offline too - mirrors the
// same "last known good copy" idea as DatabaseManager's ApiCache table,
// just for binary bytes instead of JSON text.
public class AttachmentCache {

    private static final Path CACHE_DIR = Paths.get("attachment-cache");

    private AttachmentCache() {}

    private static String keyFor(String fullUrl) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(fullUrl.getBytes(StandardCharsets.UTF_8));
            StringBuilder hex = new StringBuilder();
            for (byte b : hash) hex.append(String.format("%02x", b));
            return hex.toString();
        } catch (Exception e) {
            return String.valueOf(fullUrl.hashCode());
        }
    }

    /** The local file this URL would be cached at, preserving its original
     *  extension so image viewers / Desktop.open() still recognize the type. */
    public static File localFileFor(String fullUrl) {
        String ext = "";
        int dot = fullUrl.lastIndexOf('.');
        int slash = fullUrl.lastIndexOf('/');
        if (dot > slash && dot != -1) ext = fullUrl.substring(dot);
        return CACHE_DIR.resolve(keyFor(fullUrl) + ext).toFile();
    }

    public static boolean isCached(String fullUrl) {
        return localFileFor(fullUrl).isFile();
    }

    /** Kicks off a background download to store this attachment locally if
     *  it isn't already cached. Fire-and-forget, best-effort - "cache it if
     *  we can while we're already online viewing it" - so it never blocks
     *  the caller (typically the JavaFX thread rendering a reply). */
    public static void ensureCachedAsync(String fullUrl) {
        if (isCached(fullUrl)) return;
        new Thread(() -> downloadNow(fullUrl)).start();
    }

    private static void downloadNow(String fullUrl) {
        File target = localFileFor(fullUrl);
        try {
            Files.createDirectories(CACHE_DIR);
            URL url = URI.create(fullUrl).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setConnectTimeout(5000);
            conn.setReadTimeout(10000);
            conn.setRequestMethod("GET");
            if (conn.getResponseCode() != 200) return;

            File tmp = File.createTempFile("attachment", ".part", CACHE_DIR.toFile());
            try (InputStream in = conn.getInputStream(); OutputStream out = new FileOutputStream(tmp)) {
                in.transferTo(out);
            }
            Files.move(tmp.toPath(), target.toPath(), StandardCopyOption.REPLACE_EXISTING);
        } catch (Exception e) {
            System.err.println("[AttachmentCache] Couldn't cache attachment " + fullUrl + ": " + e.getMessage());
        }
    }
}
