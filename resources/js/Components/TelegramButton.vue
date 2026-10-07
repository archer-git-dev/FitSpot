<script setup lang="ts">
import { ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
const props = withDefaults(
    defineProps<{ mode?: "login" | "link" | "reauth"; label?: string }>(),
    { mode: "login", label: "Войти через Telegram" },
);
const page = usePage<any>(),
    busy = ref(false),
    error = ref("");
async function start() {
    busy.value = true;
    error.value = "";
    try {
        const csrf = decodeURIComponent(
            document.cookie
                .split("; ")
                .find((s) => s.startsWith("XSRF-TOKEN="))
                ?.split("=")
                .slice(1)
                .join("=") || "",
        );
        const res = await fetch("/auth/telegram/nonce", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-XSRF-TOKEN": csrf,
            },
            body: JSON.stringify({ mode: props.mode }),
        });
        if (!res.ok)
            throw new Error("Telegram недоступен. Попробуйте позднее.");
        const { nonce } = await res.json();
        if (!(window as any).Telegram?.Login) {
            await new Promise<void>((resolve, reject) => {
                const s = document.createElement("script");
                s.src = "https://oauth.telegram.org/js/telegram-login.js";
                s.onload = () => resolve();
                s.onerror = () =>
                    reject(new Error("Не удалось загрузить Telegram."));
                document.head.appendChild(s);
            });
        }
        (window as any).Telegram.Login.init(
            {
                client_id: Number(page.props.telegram.clientId),
                scope: ["profile"],
                nonce,
                lang: "ru",
            },
            (data: any) => {
                if (!data.id_token) {
                    error.value = "Вход не завершён. Попробуйте ещё раз.";
                    busy.value = false;
                    return;
                }
                router.post(
                    "/auth/telegram/verify",
                    { id_token: data.id_token },
                    {
                        onFinish: () => (busy.value = false),
                        onError: () =>
                            (error.value = "Не удалось подтвердить Telegram."),
                    },
                );
            },
        );
        (window as any).Telegram.Login.open();
        busy.value = false;
    } catch (e) {
        error.value = e instanceof Error ? e.message : "Ошибка Telegram";
        busy.value = false;
    }
}
</script>
<template>
    <div>
        <button
            v-if="page.props.telegram.enabled"
            type="button"
            class="btn secondary w-full"
            :disabled="busy"
            @click="start"
        >
            {{ label }}
        </button>
        <p v-else class="muted">
            Вход через Telegram пока недоступен: интеграция не настроена.
        </p>
        <p v-if="error" role="alert" class="error">{{ error }}</p>
    </div>
</template>
