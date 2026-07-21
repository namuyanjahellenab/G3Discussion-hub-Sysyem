<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ml_gateway' => [
        'url' => env('ML_GATEWAY_URL', 'http://127.0.0.1:5000'),
        'token' => env('ML_GATEWAY_TOKEN'),
        // Every one of these calls runs synchronously while a page is
        // rendering (classify during chat send, trendingGroups on the
        // Groups page, recommendTopics on Recommendations, topicShareLinks
        // on a topic page) - an 8s ceiling meant EVERY one of those pages
        // blocked for that long whenever the gateway was slow/unreachable,
        // not just chat. A healthy gateway measured ~120ms end to end
        // (verified with a live /classify call), so 0.5s leaves ~4x
        // headroom above that while still failing fast when it's down -
        // confirmed by timing GroupController::index -> trendingGroups
        // directly before/after (was consistently ~2.1-3s at the old
        // default, this cuts it to ~0.5s worst case). Every caller already
        // falls back gracefully on null/timeout (see GroupBrowseService,
        // generateRecommendations, etc.) - lowering this only changes how
        // long that fallback takes to kick in, not what happens after.
        'timeout' => env('ML_GATEWAY_TIMEOUT', 0.5),
        // classify() is on the hottest path of all (every chat send), so it
        // gets the same tight budget as the shared default above.
        'classify_timeout' => env('ML_GATEWAY_CLASSIFY_TIMEOUT', 0.5),
        // PDF export is a deliberate, occasional "download" click, not
        // something hit by casual page browsing, and legitimately can take
        // longer than the other calls (real PDF rendering, not a lookup) -
        // it keeps the old generous budget instead of inheriting the
        // shortened default above, so a slow-but-working render still
        // completes via the richer Python renderer rather than being cut
        // off early into the plainer local Dompdf fallback.
        'pdf_timeout' => env('ML_GATEWAY_PDF_TIMEOUT', 8),
    ],

];
