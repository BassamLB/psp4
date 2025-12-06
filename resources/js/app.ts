import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
});

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Ensure `window.Echo` is available for legacy code that expects a global Echo instance.
// `configureEcho` registers the Echo instance with the Vue plugin, but some
// components (older or simpler ones) read `window.Echo` directly. Create a
// global Echo only if one isn't already present to avoid double-connections.
(async () => {
    if (typeof window === 'undefined' || (window as any).Echo) return;

    try {
        const Echo = (await import('laravel-echo')).default;
        const Pusher = (await import('pusher-js')).default;
        (window as any).Pusher = Pusher;

        const reverbHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
        const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
        const scheme = import.meta.env.VITE_REVERB_SCHEME ?? (location.protocol === 'https:' ? 'https' : 'http');
        const key = import.meta.env.VITE_REVERB_APP_KEY || '';

        (window as any).Echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: scheme === 'https',
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: ({
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                withCredentials: true,
            } as any),
        });

        console.info('window.Echo initialized by app.ts');
    } catch (err) {
        console.warn('Failed to initialize window.Echo', err);
    }
})();
