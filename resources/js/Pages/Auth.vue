<script setup lang="ts">
import { computed } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import TelegramButton from "../Components/TelegramButton.vue";
const props = defineProps<{ mode: string; token?: string; email?: string }>(),
    page = usePage<any>();
const form = useForm({
    name: "",
    email: props.email || page.props.auth.user?.email || "",
    password: "",
    password_confirmation: "",
    token: props.token || "",
    remember: false,
});
const verify = useForm({}),
    correct = useForm({ email: page.props.auth.user?.email || "" });
const title = computed(
    () =>
        (
            ({
                login: "С возвращением",
                register: "Начните с FitSpot",
                forgot: "Восстановить доступ",
                reset: "Новый пароль",
                verify: "Подтвердите email",
                "telegram-email": "Завершите регистрацию",
            }) as Record<string, string>
        )[props.mode],
);
function submit() {
    const path = (
        {
            login: "/login",
            register: "/register",
            forgot: "/forgot-password",
            reset: "/reset-password",
            "telegram-email": "/auth/telegram/complete",
        } as Record<string, string>
    )[props.mode];
    if (path)
        form.post(path, {
            onFinish: () => form.reset("password", "password_confirmation"),
        });
}
</script>
<template>
    <Head :title="title" />
    <main class="min-h-screen grid lg:grid-cols-2">
        <aside
            class="hidden lg:flex flex-col justify-between bg-[#103e2e] p-14 text-white"
        >
            <div class="text-2xl font-bold">
                FitSpot<span class="text-[#72d8ab]">.</span>
            </div>
            <div>
                <p
                    class="uppercase tracking-[.25em] text-sm text-[#9bceba] mb-6"
                >
                    Пространство тренера
                </p>
                <h1 class="text-5xl leading-tight text-white max-w-lg">
                    Меньше рутины.<br />Больше времени<br />на людей.
                </h1>
                <p class="mt-8 text-[#bdd7cb] max-w-sm">
                    Настройте рабочий график и услуги — всё важное для вашей
                    практики в одном месте.
                </p>
            </div>
            <p class="text-[#9bceba] text-sm">Ваш ритм. Ваши правила.</p>
        </aside>
        <section class="flex items-center justify-center px-5 py-10">
            <div class="w-full max-w-md">
                <Link
                    href="/login"
                    class="text-2xl font-bold lg:hidden block mb-10"
                    >FitSpot<span class="text-green-700">.</span></Link
                >
                <h1>{{ title }}</h1>
                <p class="muted mt-2 mb-8">
                    {{
                        mode === "verify"
                            ? "Письмо отправлено на " +
                              page.props.auth.user?.email
                            : "Панель для вашей тренерской практики"
                    }}
                </p>
                <p
                    v-if="page.props.flash.success || page.props.flash.status"
                    class="p-4 mb-5 rounded-xl bg-green-50 text-green-800"
                    role="status"
                >
                    {{ page.props.flash.success || page.props.flash.status }}
                </p>
                <template v-if="mode === 'verify'"
                    ><div class="card">
                        <p class="muted mb-5">
                            Откройте ссылку в письме, чтобы перейти к настройке
                            пространства.
                        </p>
                        <button
                            class="btn w-full"
                            :disabled="verify.processing"
                            @click="
                                verify.post('/email/verification-notification')
                            "
                        >
                            Отправить письмо повторно
                        </button>
                        <p class="muted mt-3">
                            Повторная отправка — не чаще раза в минуту.
                        </p>
                        <form
                            class="mt-6"
                            @submit.prevent="correct.post('/email/correct')"
                        >
                            <label for="correct-email">Исправить email</label
                            ><input
                                id="correct-email"
                                v-model="correct.email"
                                type="email"
                                required
                            />
                            <p class="error">{{ correct.errors.email }}</p>
                            <button
                                class="btn secondary mt-3"
                                :disabled="correct.processing"
                            >
                                Сохранить и отправить письмо
                            </button>
                        </form>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="mt-6 text-green-800"
                        >Выйти</Link
                    ></template
                >
                <template v-else
                    ><form @submit.prevent="submit">
                        <div
                            v-if="
                                mode === 'register' || mode === 'telegram-email'
                            "
                            class="field"
                        >
                            <label for="name">Имя</label
                            ><input
                                id="name"
                                v-model="form.name"
                                autocomplete="name"
                                required
                                maxlength="100"
                            />
                            <p class="error">{{ form.errors.name }}</p>
                        </div>
                        <div class="field">
                            <label for="email">Email</label
                            ><input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                            />
                            <p class="error">{{ form.errors.email }}</p>
                        </div>
                        <div
                            v-if="
                                mode === 'register' ||
                                mode === 'login' ||
                                mode === 'reset'
                            "
                            class="field"
                        >
                            <label for="password">Пароль</label
                            ><input
                                id="password"
                                v-model="form.password"
                                type="password"
                                :autocomplete="
                                    mode === 'login'
                                        ? 'current-password'
                                        : 'new-password'
                                "
                                :minlength="mode === 'login' ? undefined : 12"
                                required
                            />
                            <p v-if="mode !== 'login'" class="muted mt-2">
                                Не менее 12 символов.
                            </p>
                            <p class="error">{{ form.errors.password }}</p>
                        </div>
                        <div
                            v-if="mode === 'register' || mode === 'reset'"
                            class="field"
                        >
                            <label for="password_confirmation"
                                >Повторите пароль</label
                            ><input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                            />
                        </div>
                        <div
                            v-if="mode === 'login'"
                            class="flex justify-between mb-6 text-sm"
                        >
                            <label class="font-normal"
                                ><input
                                    v-model="form.remember"
                                    type="checkbox"
                                />
                                Запомнить меня</label
                            ><Link
                                href="/forgot-password"
                                class="text-green-800"
                                >Забыли пароль?</Link
                            >
                        </div>
                        <button class="btn w-full" :disabled="form.processing">
                            {{
                                mode === "login"
                                    ? "Войти"
                                    : mode === "forgot"
                                      ? "Отправить ссылку"
                                      : mode === "reset"
                                        ? "Сохранить пароль"
                                        : mode === "telegram-email"
                                          ? "Подтвердить email"
                                          : "Создать аккаунт"
                            }}
                        </button>
                    </form>
                    <div
                        v-if="mode === 'login' || mode === 'register'"
                        class="mt-5"
                    >
                        <div class="flex items-center gap-4 muted my-5">
                            <hr class="grow border-slate-200" />
                            или
                            <hr class="grow border-slate-200" />
                        </div>
                        <TelegramButton />
                        <p class="mt-7 text-center muted">
                            {{
                                mode === "login"
                                    ? "Ещё нет аккаунта?"
                                    : "Уже есть аккаунт?"
                            }}
                            <Link
                                :href="
                                    mode === 'login' ? '/register' : '/login'
                                "
                                class="text-green-800 font-semibold"
                                >{{
                                    mode === "login"
                                        ? "Зарегистрироваться"
                                        : "Войти"
                                }}</Link
                            >
                        </p>
                    </div>
                    <Link v-else href="/login" class="block mt-6 text-green-800"
                        >Вернуться ко входу</Link
                    ></template
                >
            </div>
        </section>
    </main>
</template>
