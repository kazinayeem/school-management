import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { MotionConfig } from 'motion/react';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { applyLocale, setLocale, type Locale } from './lib/i18n';

declare global {
    const route: typeof routeFn;
}

let appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const shared = props.initialPage.props as { name?: string };
        if (shared.name) appName = shared.name;

        // "reduce motion" in the person's system settings turns the movement off everywhere.
        root.render(
            <MotionConfig reducedMotion="user">
                <App {...props} />
            </MotionConfig>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Restore the saved language and text direction on load.
try {
    setLocale((localStorage.getItem('locale') as Locale) || 'en');
} catch {
    applyLocale('en');
}
