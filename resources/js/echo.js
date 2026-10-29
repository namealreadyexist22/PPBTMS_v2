import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Live notifications (Reverb). Built without a Reverb key, or failing to start, the app still works:
// an error here would stop the rest of app.js (jQuery, DataTables, ...) from loading.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.warn('Live notifications are off: Reverb could not start.', error);
    }
} else {
    console.warn('Live notifications are off: VITE_REVERB_APP_KEY was empty when the assets were built.');
}
