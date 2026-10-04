import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
createInertiaApp({
    title: (title) => (title ? `${title} · FitSpot` : "FitSpot"),
    resolve: async (name) => {
        const pages = import.meta.glob("./Pages/*.vue");
        return ((await pages[`./Pages/${name}.vue`]()) as { default: unknown })
            .default as any;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: "#137c59" },
});
