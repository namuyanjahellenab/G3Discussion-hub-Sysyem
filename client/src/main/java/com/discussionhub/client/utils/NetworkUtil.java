package com.discussionhub.client.utils;

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;

public class NetworkUtil {

    // TODO: point this at our own Laravel deployment once it's live
    private static final String PROBE_URL = "https://www.google.com";
    private static final int CONNECT_TIMEOUT_MS = 2000;
    private static final int READ_TIMEOUT_MS = 2000;

    public static boolean isNetworkAvailable() {
        try {
            URL url = new URL(PROBE_URL);
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();

            connection.setConnectTimeout(CONNECT_TIMEOUT_MS);
            connection.setReadTimeout(READ_TIMEOUT_MS);
            connection.setRequestMethod("HEAD");

            int responseCode = connection.getResponseCode();
            return (responseCode == HttpURLConnection.HTTP_OK);

        } catch (IOException e) {
            return false;
        }
    }
}