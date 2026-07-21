import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Only the group chat page (student/messages.blade.php) actually uses
// window.Echo - every other page was paying for a WebSocket connection
// attempt to Reverb on load regardless, with nothing listening on it.
// Constructing Echo here unconditionally meant that attempt (and Pusher-js's
// automatic retry loop on failure) ran on EVERY page navigation site-wide.
// Deferred to an explicit init call so it only happens on the one page that
// needs it.
window.initEcho = function initEcho() {
    if (window.Echo) return window.Echo;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
};
