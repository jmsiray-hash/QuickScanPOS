import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const meta = name => document.querySelector(`meta[name="${name}"]`)?.content || '';
const key = meta('reverb-key');
const host = meta('reverb-host');

if (key && host) {
    const port = Number(meta('reverb-port') || 443);
    const scheme = meta('reverb-scheme') || 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    if (meta('authenticated') === 'true') {
        window.Echo.private('pos-sync').listen('.pos.sync', event => {
            window.dispatchEvent(new CustomEvent('pos:sync', { detail: event }));
        });
    }
}
