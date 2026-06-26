package com.discussionhub.client.utils;

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;

public class NetworkUtil {

    public static boolean isNetworkAvailable() {
        try {
            // i will replace this with my actual Laravel deployment URL later!
            URL url = new URL("https://www.google.com");
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();

            connection.setConnectTimeout(2000);
            connection.setReadTimeout(2000);
            connection.setRequestMethod("HEAD");

            int responseCode = connection.getResponseCode();
            return (responseCode == HttpURLConnection.HTTP_OK);

        } catch (IOException e) {
            return false;
        }
    }
}