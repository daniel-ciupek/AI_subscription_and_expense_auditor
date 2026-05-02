import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Recharts 3.x emits a width(-1)/height(-1) warning from its
// ResponsiveContainer when it measures the parent before the first paint
// cycle finishes. The chart renders correctly on the next tick — the
// warning is purely cosmetic, but it spams the console on every page
// load. Filter that one specific message.
const originalWarn = console.warn.bind(console);
console.warn = (...args: unknown[]) => {
    if (
        typeof args[0] === 'string' &&
        args[0].includes('width(-1) and height(-1) of chart')
    ) {
        return;
    }
    originalWarn(...args);
};

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
