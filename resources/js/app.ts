import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

createInertiaApp({
    title: (title) => (title ? `${title} · Clínica` : 'Clínica'),
    pages: './domains',
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el!);
    },
    progress: {
        color: '#2563eb',
        showSpinner: false,
    },
});
