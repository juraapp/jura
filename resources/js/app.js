import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { initFlowbite } from 'flowbite';
import { createApp, h } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Flowbite's dropdowns/modals/tooltips are wired via data-attributes and only
// auto-init on DOMContentLoaded. Inertia swaps the page on every client-side
// navigation without a full reload, so re-run the initializer after each one.
router.on('navigate', () => initFlowbite());

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
}).then(() => initFlowbite());
