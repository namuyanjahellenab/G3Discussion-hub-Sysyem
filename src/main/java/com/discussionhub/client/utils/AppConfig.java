package com.discussionhub.client.utils;

// Single source of truth for the backend this desktop client talks to.
// Every controller used to hardcode its own copy of BASE_URL (and a few
// inlined the literal string instead of even that) - one value changing
// meant editing 15+ files. Everything now reads from here instead.
public class AppConfig {

    public static final String BASE_URL = "https://laravel-web-production-80d8.up.railway.app";
    public static final String API_URL = BASE_URL + "/api";
    public static final String PROBE_URL = API_URL + "/ping";

    // Reverb (Laravel's WebSocket broadcasting server) is not yet deployed
    // as its own Railway service - these still point at a local dev server.
    // GroupChatController's realtime push will silently fail to connect
    // against these until Reverb is actually deployed and these are updated
    // to match; the 10s poll fallback (AUTO_REFRESH_INTERVAL) covers that
    // gap in the meantime.
    public static final String REVERB_APP_KEY = "discussionhublocalkey";
    public static final String REVERB_HOST = "127.0.0.1";
    public static final int REVERB_WS_PORT = 8080;
    public static final boolean REVERB_USE_TLS = false;

    private AppConfig() {
    }
}
