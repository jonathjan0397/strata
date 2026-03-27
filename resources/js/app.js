import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Shared hosting (CWP/Apache) blocks PATCH/PUT/DELETE verbs via ModSecurity.
// Intercept all Inertia requests and convert them to POST + _method spoofing,
// which Laravel's MethodOverride middleware handles transparently.
const _origVisit = router.visit.bind(router);
router.visit = function (url, options = {}) {
    const method = (options.method || 'get').toLowerCase();
    if (method === 'patch' || method === 'put' || method === 'delete') {
        options = {
            ...options,
            method: 'post',
            data: { ...(options.data ?? {}), _method: method },
        };
    }
    return _origVisit(url, options);
};

createInertiaApp({
    title: (title) => title ? `${title} — Strata` : 'Strata',

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },

    progress: {
        color: '#6366f1',   // indigo — matches Strata accent
    },
});
