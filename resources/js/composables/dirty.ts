import { onMounted, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
export function useDirty(check: () => boolean) {
    const guard = (e: BeforeUnloadEvent) => {
        if (check()) {
            e.preventDefault();
            e.returnValue = "";
        }
    };
    let remove: () => void;
    onMounted(() => {
        window.addEventListener("beforeunload", guard);
        remove = router.on("before", (e) => {
            if (
                e.detail.visit.method === "get" &&
                check() &&
                !confirm("Изменения не сохранены. Покинуть страницу?")
            )
                e.preventDefault();
        });
    });
    onUnmounted(() => {
        window.removeEventListener("beforeunload", guard);
        remove?.();
    });
}
