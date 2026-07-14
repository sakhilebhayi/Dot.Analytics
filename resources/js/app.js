import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

/**
 * Listen for intelligence engine completion events on the team's private channel.
 * Dispatches a browser CustomEvent so any Livewire component or Alpine.js
 * component can react without polling.
 */
if (window.teamId) {
    window.Echo.private(`team.${window.teamId}.intelligence`)
        .listen('.engine.completed', (data) => {
            window.dispatchEvent(new CustomEvent('intelligence:engine-completed', { detail: data }));
        });

    window.Echo.private(`team.${window.teamId}.alerts`)
        .listen('.alert.triggered', (data) => {
            window.dispatchEvent(new CustomEvent('intelligence:alert', { detail: data }));
        });
}

