package com.discussionhub.client.utils;

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;

public class NetworkUtil {

    // Must probe our own Laravel backend, not general internet connectivity —
    // "online" here means "the app's server is reachable", since that's what
    // every screen actually depends on. Pinging google.com would say ONLINE
    // even while the local Laravel server itself is down.
    //
    // /api/ping is deliberately outside every middleware (no Sanctum, no
    // session) so it resolves in milliseconds. Probing an authenticated
    // route here (the old /api/login GET, which just 405s) still boots the
    // full auth stack before rejecting the method - measured at 1.5-4s under
    // the dev server, which blew past the timeout below and reported a
    // false OFFLINE even with the server fully up.
    private static final String PROBE_URL = "http://127.0.0.1:8000/api/ping";
    private static final int CONNECT_TIMEOUT_MS = 2500;
    private static final int READ_TIMEOUT_MS = 2500;

    public static boolean isNetworkAvailable() {
        try {
            URL url = URI.create(PROBE_URL).toURL();
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();

            connection.setConnectTimeout(CONNECT_TIMEOUT_MS);
            connection.setReadTimeout(READ_TIMEOUT_MS);
            connection.setRequestMethod("GET");

            // Any response at all (even a 405/404) proves the server is up;
            // only a connection failure means truly offline.
            connection.getResponseCode();
            return true;

        } catch (IOException e) {
            return false;
        }
    }
}