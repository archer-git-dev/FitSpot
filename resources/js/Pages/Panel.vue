<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import TelegramButton from "../Components/TelegramButton.vue";
import { useDirty } from "../composables/dirty";
type Interval = { day: number; start: number; end: number };
type Service = {
    id: number;
    name: string;
    description: string | null;
    type: string;
    format: string;
    duration: number;
    price_kopecks: number;
    capacity: number;
    location: string | null;
    active: boolean;
};
const props = defineProps<{
    section: string;
    workspace: any;
    timezones: string[];
    publicUrl: string;
    warnings: number[];
    telegramLinked: boolean;
    hasPassword: boolean;
}>();
const page = usePage<any>(),
    menu = ref(false),
    editing = ref<number | null>(null),
    showService = ref(false);
const navigation = [
    ["overview", "Обзор"],
    ["profile", "Профиль"],
    ["schedule", "Недельный график"],
    ["services", "Услуги"],
    ["rules", "Правила записи"],
    ["access", "Настройки доступа"],
];
const title = computed(
    () => navigation.find((n) => n[0] === props.section)?.[1] || "Обзор",
);
const profile = useForm({
    name: "",
    slug: "",
    timezone: "Europe/Moscow",
    description: "",
    phone: "",
    location_name: "",
    address: "",
    photo: null as File | null,
    remove_photo: false,
});
const schedule = useForm({ intervals: [] as Interval[] });
const rules = useForm({
    buffer_before: 0,
    buffer_after: 10,
    lead_minutes: 120,
    horizon_days: 30,
    slot_step: 15,
    cancel_minutes: 720,
    reschedule_minutes: 720,
});
const service = useForm({
    name: "",
    description: "",
    type: "personal",
    format: "in_person",
    duration: 60,
    price: "",
    capacity: 1,
    location: "",
    active: true,
});
const access = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
    email: page.props.auth.user.email,
});
const days = [
    "Понедельник",
    "Вторник",
    "Среда",
    "Четверг",
    "Пятница",
    "Суббота",
    "Воскресенье",
];
const copyTargets = ref<number[]>([]),
    copySource = ref(1);
const changingStatus = ref<number | null>(null);
function toggleStatus(item: Service) {
    if (changingStatus.value !== null) return;
    changingStatus.value = item.id;
    router.patch(
        `/app/services/${item.id}/status`,
        { active: !item.active },
        {
            onFinish: () => {
                changingStatus.value = null;
            },
        },
    );
}
function hydrate() {
    const w = props.workspace;
    for (const key of [
        "name",
        "slug",
        "timezone",
        "description",
        "phone",
        "location_name",
        "address",
    ] as const)
        profile[key] = w[key] || "";
    profile.photo = null;
    profile.remove_photo = false;
    profile.defaults();
    schedule.intervals = w.intervals.map((i: Interval) => ({
        day: i.day,
        start: i.start,
        end: i.end,
    }));
    schedule.defaults();
    for (const k of Object.keys(rules.data()) as string[])
        (rules as any)[k] = w.rules[k];
    rules.defaults();
}
hydrate();
watch(() => props.workspace, hydrate);
useDirty(
    () =>
        profile.isDirty ||
        schedule.isDirty ||
        rules.isDirty ||
        (showService.value && service.isDirty) ||
        access.isDirty,
);
const ready = computed(
    () =>
        !!props.workspace.name &&
        !!props.workspace.slug &&
        !!props.workspace.timezone &&
        props.workspace.intervals.length > 0 &&
        props.workspace.services.some((s: Service) => s.active),
);
function add(day: number) {
    const items = schedule.intervals.filter((i) => i.day === day);
    if (items.length < 8)
        schedule.intervals.push({
            day,
            start: items.length
                ? Math.min(items[items.length - 1].end + 60, 1380)
                : 540,
            end: items.length
                ? Math.min(items[items.length - 1].end + 120, 1440)
                : 1080,
        });
}
function displayTime(minutes: number) {
    return `${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;
}
function inputTime(item: Interval, key: "start" | "end", e: Event) {
    const value = (e.target as HTMLInputElement).value;
    const parts = value.split(":").map(Number);
    if (parts.length === 2) item[key] = parts[0] * 60 + parts[1];
}
function copy() {
    const items = schedule.intervals.filter((i) => i.day === copySource.value);
    for (const day of copyTargets.value) {
        schedule.intervals = schedule.intervals.filter((i) => i.day !== day);
        schedule.intervals.push(...items.map((i) => ({ ...i, day })));
    }
    copyTargets.value = [];
}
function edit(item?: Service) {
    if (
        showService.value &&
        service.isDirty &&
        !confirm("Закрыть несохранённую услугу?")
    )
        return;
    editing.value = item?.id || null;
    Object.assign(service, {
        name: item?.name || "",
        description: item?.description || "",
        type: item?.type || "personal",
        format: item?.format || "in_person",
        duration: item?.duration || 60,
        price: item
            ? `${Math.floor(item.price_kopecks / 100)}.${String(item.price_kopecks % 100).padStart(2, "0")}`
            : "",
        capacity: item?.capacity || 1,
        location: item?.location || "",
        active: item?.active ?? true,
    });
    service.clearErrors();
    service.defaults();
    showService.value = true;
}
function cancelService() {
    if (!service.isDirty || confirm("Закрыть без сохранения?"))
        showService.value = false;
}
function saveService() {
    service.capacity = service.type === "split" ? 2 : 1;
    const options = {
        onSuccess: () => {
            service.defaults();
            showService.value = false;
        },
    };
    if (editing.value) service.put("/app/services/" + editing.value, options);
    else service.post("/app/services", options);
}
function saveProfile() {
    profile.post("/app/profile", {
        forceFormData: true,
        onSuccess: () => {
            profile.photo = null;
            profile.defaults();
        },
    });
}
function saveAccess(path: string) {
    access.post(path, {
        onSuccess: () => {
            access.reset(
                "current_password",
                "password",
                "password_confirmation",
            );
            access.defaults();
        },
    });
}
const price = (s: Service) =>
    new Intl.NumberFormat("ru-RU", {
        style: "currency",
        currency: "RUB",
    }).format(s.price_kopecks / 100);
function photoInput(e: Event) {
    profile.photo = (e.target as HTMLInputElement).files?.[0] || null;
}
</script>
<template>
    <Head :title="title" />
    <div class="min-h-screen lg:grid lg:grid-cols-[250px_1fr]">
        <aside class="bg-white border-r border-[#e0e8e4] lg:min-h-screen">
            <div class="flex items-center justify-between p-6">
                <Link href="/app" class="text-2xl font-bold"
                    >FitSpot<span class="text-green-700">.</span></Link
                ><button
                    class="lg:hidden btn secondary"
                    @click="menu = !menu"
                    :aria-expanded="menu"
                    aria-label="Меню"
                >
                    ☰
                </button>
            </div>
            <nav :class="[menu ? 'block' : 'hidden', 'lg:block px-4 pb-5']">
                <Link
                    v-for="[key, label] in navigation"
                    :key="key"
                    :href="'/app/' + key"
                    class="block rounded-xl px-4 py-3 mb-1 text-sm font-semibold"
                    :class="
                        section === key
                            ? 'bg-green-50 text-green-800'
                            : 'text-slate-600 hover:bg-slate-50'
                    "
                    @click="menu = false"
                    >{{ label }}</Link
                ><Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="mt-8 px-4 py-3 text-sm text-slate-600"
                    >Выйти</Link
                >
            </nav>
        </aside>
        <main class="min-w-0 px-4 py-7 sm:px-8 lg:px-12">
            <div class="max-w-5xl mx-auto">
                <header class="flex items-start justify-between gap-4 mb-8">
                    <div>
                        <p class="muted mb-1">Рабочее пространство</p>
                        <h1>{{ title }}</h1>
                    </div>
                    <div class="hidden sm:block text-right">
                        <p class="font-semibold">{{ workspace.name }}</p>
                        <p class="muted">{{ workspace.timezone }}</p>
                    </div>
                </header>
                <p
                    v-if="page.props.flash.success"
                    class="rounded-xl bg-green-50 border border-green-100 text-green-800 p-4 mb-6"
                    role="status"
                >
                    {{ page.props.flash.success }}
                </p>
                <section v-if="section === 'overview'">
                    <div class="card bg-[#103e2e]! text-white! mb-6">
                        <p class="text-[#a5d2bd] text-sm mb-3">
                            ВАША ПРАКТИКА НАЧИНАЕТСЯ ЗДЕСЬ
                        </p>
                        <h2 class="text-2xl!">
                            {{
                                ready
                                    ? "Базовая настройка завершена"
                                    : "Подготовим ваше пространство"
                            }}
                        </h2>
                        <p class="text-[#c1dbce] max-w-xl">
                            {{
                                ready
                                    ? "График и услуги готовы. Онлайн-запись клиентов появится в следующем блоке."
                                    : "Заполните профиль, выберите рабочее время и добавьте услуги. Настройки можно изменить в любой момент."
                            }}
                        </p>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4 mb-6">
                        <Link href="/app/profile" class="card"
                            ><p class="muted mb-3">01 · ПРОФИЛЬ</p>
                            <h2>Ваше пространство</h2>
                            <p class="text-green-800">
                                {{
                                    workspace.name && workspace.slug
                                        ? "Заполнено"
                                        : "Настроить →"
                                }}
                            </p></Link
                        ><Link href="/app/schedule" class="card"
                            ><p class="muted mb-3">02 · ГРАФИК</p>
                            <h2>Рабочая неделя</h2>
                            <p class="text-green-800">
                                {{
                                    workspace.intervals.length
                                        ? "Интервалов: " +
                                          workspace.intervals.length
                                        : "Добавить часы →"
                                }}
                            </p></Link
                        ><Link href="/app/services" class="card"
                            ><p class="muted mb-3">03 · УСЛУГИ</p>
                            <h2>Ваши занятия</h2>
                            <p class="text-green-800">
                                {{
                                    workspace.services.filter(
                                        (s: Service) => s.active,
                                    ).length
                                        ? "Активных: " +
                                          workspace.services.filter(
                                              (s: Service) => s.active,
                                          ).length
                                        : "Добавить услугу →"
                                }}
                            </p></Link
                        >
                    </div>
                    <div class="card">
                        <h2>Будущий адрес страницы</h2>
                        <p class="break-all text-green-800 font-semibold">
                            {{ publicUrl }}
                        </p>
                        <p class="muted mt-3">
                            Клиентская страница пока не опубликована. Самозапись
                            появится в блоке Б.
                        </p>
                    </div>
                </section>
                <form
                    v-if="section === 'profile'"
                    @submit.prevent="saveProfile"
                    class="card max-w-3xl"
                >
                    <h2>Профиль тренера</h2>
                    <div class="flex items-center gap-5 mb-6">
                        <img
                            v-if="workspace.photo_url && !profile.remove_photo"
                            :src="
                                workspace.photo_url +
                                '?v=' +
                                workspace.updated_at
                            "
                            class="size-20 rounded-2xl object-cover"
                            alt="Фото тренера"
                        />
                        <div>
                            <label for="photo">Фото</label
                            ><input
                                id="photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="photoInput"
                            />
                            <p class="muted mt-2">
                                JPEG, PNG или WebP, до 5 МБ.
                            </p>
                            <label
                                v-if="workspace.photo_url"
                                class="mt-3 font-normal"
                                ><input
                                    v-model="profile.remove_photo"
                                    type="checkbox"
                                />
                                Удалить фото</label
                            >
                            <p class="error">{{ profile.errors.photo }}</p>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-x-5">
                        <div class="field">
                            <label for="trainer-name">Имя</label
                            ><input
                                id="trainer-name"
                                v-model="profile.name"
                                required
                                maxlength="100"
                            />
                            <p class="error">{{ profile.errors.name }}</p>
                        </div>
                        <div class="field">
                            <label for="slug">Адрес страницы</label
                            ><input
                                id="slug"
                                v-model="profile.slug"
                                required
                                minlength="3"
                                maxlength="40"
                            />
                            <p class="muted mt-2">
                                Латинские буквы, цифры и дефисы.
                            </p>
                            <p class="error">{{ profile.errors.slug }}</p>
                        </div>
                        <div class="field">
                            <label for="timezone">Часовой пояс</label
                            ><select id="timezone" v-model="profile.timezone">
                                <option v-for="tz in timezones" :key="tz">
                                    {{ tz }}
                                </option>
                            </select>
                            <p class="error">{{ profile.errors.timezone }}</p>
                        </div>
                        <div class="field">
                            <label for="phone">Телефон</label
                            ><input
                                id="phone"
                                v-model="profile.phone"
                                type="tel"
                                placeholder="+79991234567"
                            />
                            <p class="error">{{ profile.errors.phone }}</p>
                        </div>
                    </div>
                    <div class="field">
                        <label for="description">О себе</label
                        ><textarea
                            id="description"
                            v-model="profile.description"
                            rows="4"
                            maxlength="2000"
                        />
                        <p class="error">{{ profile.errors.description }}</p>
                    </div>
                    <div class="field">
                        <label for="location_name">Место проведения</label
                        ><input
                            id="location_name"
                            v-model="profile.location_name"
                            maxlength="200"
                        />
                        <p class="error">{{ profile.errors.location_name }}</p>
                    </div>
                    <div class="field">
                        <label for="address">Адрес</label
                        ><input
                            id="address"
                            v-model="profile.address"
                            maxlength="500"
                        />
                        <p class="error">{{ profile.errors.address }}</p>
                    </div>
                    <button class="btn" :disabled="profile.processing">
                        Сохранить профиль
                    </button>
                </form>
                <section v-if="section === 'schedule'">
                    <p class="muted mb-5">
                        Время указано в часовом поясе {{ workspace.timezone }}.
                        Перерывы — промежутки между интервалами.
                    </p>
                    <form @submit.prevent="schedule.put('/app/schedule')">
                        <div
                            class="card mb-4"
                            v-for="(day, index) in days"
                            :key="day"
                        >
                            <div
                                class="flex justify-between gap-3 items-center mb-3"
                            >
                                <h2 class="mb-0!">{{ day }}</h2>
                                <button
                                    type="button"
                                    class="text-sm font-semibold text-green-800"
                                    :disabled="
                                        schedule.intervals.filter(
                                            (i) => i.day === index + 1,
                                        ).length >= 8
                                    "
                                    @click="add(index + 1)"
                                >
                                    + Интервал
                                </button>
                            </div>
                            <p
                                v-if="
                                    !schedule.intervals.some(
                                        (i) => i.day === index + 1,
                                    )
                                "
                                class="muted"
                            >
                                Выходной
                            </p>
                            <div
                                v-for="item in schedule.intervals.filter(
                                    (i) => i.day === index + 1,
                                )"
                                :key="schedule.intervals.indexOf(item)"
                                class="grid grid-cols-[1fr_1fr_auto] gap-3 items-end mb-3"
                            >
                                <div>
                                    <label
                                        :for="
                                            'start-' +
                                            schedule.intervals.indexOf(item)
                                        "
                                        >Начало</label
                                    ><input
                                        :id="
                                            'start-' +
                                            schedule.intervals.indexOf(item)
                                        "
                                        type="time"
                                        :value="displayTime(item.start)"
                                        @input="
                                            inputTime(item, 'start', $event)
                                        "
                                        required
                                    />
                                </div>
                                <div>
                                    <label
                                        :for="
                                            'end-' +
                                            schedule.intervals.indexOf(item)
                                        "
                                        >Окончание</label
                                    ><input
                                        :id="
                                            'end-' +
                                            schedule.intervals.indexOf(item)
                                        "
                                        :type="
                                            item.end === 1440 ? 'text' : 'time'
                                        "
                                        :value="displayTime(item.end)"
                                        @input="inputTime(item, 'end', $event)"
                                        required
                                    /><button
                                        type="button"
                                        class="text-xs text-green-800 mt-1"
                                        @click="
                                            item.end =
                                                item.end === 1440 ? 1380 : 1440
                                        "
                                    >
                                        {{
                                            item.end === 1440
                                                ? "До 23:00"
                                                : "До 24:00"
                                        }}
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="btn secondary mb-6"
                                    aria-label="Удалить интервал"
                                    @click="
                                        schedule.intervals.splice(
                                            schedule.intervals.indexOf(item),
                                            1,
                                        )
                                    "
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                        <div class="card mb-5">
                            <h2>Скопировать график дня</h2>
                            <label for="copy-source">Из дня</label
                            ><select id="copy-source" v-model="copySource">
                                <option
                                    v-for="(day, index) in days"
                                    :value="index + 1"
                                >
                                    {{ day }}
                                </option>
                            </select>
                            <div class="flex flex-wrap gap-3 my-4">
                                <label
                                    v-for="(day, index) in days"
                                    class="font-normal"
                                    ><input
                                        v-model="copyTargets"
                                        type="checkbox"
                                        :value="index + 1"
                                        :disabled="copySource === index + 1"
                                    />
                                    {{ day }}</label
                                >
                            </div>
                            <button
                                type="button"
                                class="btn secondary"
                                @click="copy"
                                :disabled="!copyTargets.length"
                            >
                                Скопировать
                            </button>
                        </div>
                        <div
                            v-if="Object.keys(schedule.errors).length"
                            role="alert"
                            class="error mb-4"
                        >
                            <p v-for="error in schedule.errors">{{ error }}</p>
                        </div>
                        <button class="btn" :disabled="schedule.processing">
                            Сохранить график
                        </button>
                    </form>
                </section>
                <form
                    v-if="section === 'rules'"
                    @submit.prevent="rules.put('/app/rules')"
                    class="card max-w-3xl"
                >
                    <h2>Общие правила для всех услуг</h2>
                    <div class="grid sm:grid-cols-2 gap-x-5">
                        <div
                            v-for="[key, label, min, max] in [
                                [
                                    'buffer_before',
                                    'Буфер до занятия, минут',
                                    0,
                                    120,
                                ],
                                [
                                    'buffer_after',
                                    'Буфер после занятия, минут',
                                    0,
                                    120,
                                ],
                                [
                                    'lead_minutes',
                                    'Запись минимум за, минут',
                                    0,
                                    10080,
                                ],
                                [
                                    'horizon_days',
                                    'Горизонт записи, дней',
                                    1,
                                    90,
                                ],
                            ] as const"
                            class="field"
                        >
                            <label :for="key">{{ label }}</label
                            ><input
                                :id="key"
                                v-model.number="rules[key]"
                                type="number"
                                :min="min"
                                :max="max"
                                required
                            />
                            <p class="error">{{ rules.errors[key] }}</p>
                        </div>
                        <div class="field">
                            <label for="slot_step">Шаг начала, минут</label
                            ><select
                                id="slot_step"
                                v-model.number="rules.slot_step"
                            >
                                <option
                                    v-for="step in [5, 10, 15, 20, 30, 60]"
                                    :value="step"
                                >
                                    {{ step }}
                                </option>
                            </select>
                            <p class="error">{{ rules.errors.slot_step }}</p>
                        </div>
                        <div class="field">
                            <label for="cancel">Отмена минимум за, часов</label
                            ><input
                                id="cancel"
                                :value="rules.cancel_minutes / 60"
                                type="number"
                                min="0"
                                max="168"
                                step="any"
                                @input="
                                    rules.cancel_minutes = Math.round(
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ) * 60,
                                    )
                                "
                            />
                            <p class="error">
                                {{ rules.errors.cancel_minutes }}
                            </p>
                        </div>
                        <div class="field">
                            <label for="reschedule"
                                >Перенос минимум за, часов</label
                            ><input
                                id="reschedule"
                                :value="rules.reschedule_minutes / 60"
                                type="number"
                                min="0"
                                max="168"
                                step="any"
                                @input="
                                    rules.reschedule_minutes = Math.round(
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ) * 60,
                                    )
                                "
                            />
                            <p class="error">
                                {{ rules.errors.reschedule_minutes }}
                            </p>
                        </div>
                    </div>
                    <p class="muted mb-6">
                        Буферы занимают ваше рабочее время. Эти правила начнут
                        применяться при подключении самозаписи.
                    </p>
                    <button class="btn" :disabled="rules.processing">
                        Сохранить правила
                    </button>
                </form>
                <section v-if="section === 'services'">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 mb-5"
                    >
                        <p class="muted">Цена указана за одного участника.</p>
                        <button class="btn" @click="edit()">
                            Добавить услугу
                        </button>
                    </div>
                    <div
                        v-if="!workspace.services.length"
                        class="card text-center py-12"
                    >
                        <h2>Пока нет услуг</h2>
                        <p class="muted mb-5">
                            Добавьте первое занятие, которое вы проводите.
                        </p>
                        <button class="btn secondary" @click="edit()">
                            Добавить первую услугу
                        </button>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <article
                            v-for="item in workspace.services as Service[]"
                            :key="item.id"
                            class="card"
                        >
                            <div class="flex justify-between gap-3">
                                <h2>{{ item.name }}</h2>
                                <span
                                    class="text-xs rounded-full px-3 py-1 h-fit"
                                    :class="
                                        item.active
                                            ? 'bg-green-50 text-green-800'
                                            : 'bg-slate-100 text-slate-600'
                                    "
                                    >{{
                                        item.active ? "Активна" : "Отключена"
                                    }}</span
                                >
                            </div>
                            <p class="muted">
                                {{
                                    item.type === "personal"
                                        ? "Персональная"
                                        : "Сплит"
                                }}
                                ·
                                {{
                                    item.format === "online" ? "Онлайн" : "Очно"
                                }}
                                · {{ item.capacity }} участн.
                            </p>
                            <div
                                class="flex justify-between my-5 font-semibold"
                            >
                                <span>{{ item.duration }} мин.</span
                                ><span>{{ price(item) }}</span>
                            </div>
                            <p
                                v-if="warnings.includes(item.id)"
                                class="text-amber-800 text-sm mb-4"
                            >
                                Услуга с буферами пока не помещается в рабочий
                                график.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <button
                                    class="btn secondary"
                                    @click="edit(item)"
                                >
                                    Изменить</button
                                ><button
                                    class="text-sm text-slate-600"
                                    :disabled="changingStatus !== null"
                                    @click="toggleStatus(item)"
                                >
                                    {{ item.active ? "Отключить" : "Включить" }}
                                </button>
                            </div>
                        </article>
                    </div>
                    <form
                        v-if="showService"
                        @submit.prevent="saveService"
                        class="card mt-6"
                        aria-label="Редактор услуги"
                    >
                        <h2>
                            {{
                                editing
                                    ? "Редактирование услуги"
                                    : "Новая услуга"
                            }}
                        </h2>
                        <div class="field">
                            <label for="service-name">Название</label
                            ><input
                                id="service-name"
                                v-model="service.name"
                                required
                                maxlength="100"
                            />
                            <p class="error">{{ service.errors.name }}</p>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-x-5">
                            <div class="field">
                                <label for="type">Тип</label
                                ><select
                                    id="type"
                                    v-model="service.type"
                                    @change="
                                        service.capacity =
                                            service.type === 'split' ? 2 : 1
                                    "
                                >
                                    <option value="personal">
                                        Персональная
                                    </option>
                                    <option value="split">Сплит</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="format">Формат</label
                                ><select id="format" v-model="service.format">
                                    <option value="in_person">Очно</option>
                                    <option value="online">Онлайн</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="duration">Длительность, минут</label
                                ><input
                                    id="duration"
                                    v-model.number="service.duration"
                                    type="number"
                                    min="15"
                                    max="240"
                                    step="5"
                                    required
                                />
                                <p class="error">
                                    {{ service.errors.duration }}
                                </p>
                            </div>
                            <div class="field">
                                <label for="price">Цена за участника, ₽</label
                                ><input
                                    id="price"
                                    v-model="service.price"
                                    inputmode="decimal"
                                    required
                                    placeholder="2500.00"
                                />
                                <p class="error">{{ service.errors.price }}</p>
                            </div>
                        </div>
                        <div class="field">
                            <label for="service-description">Описание</label
                            ><textarea
                                id="service-description"
                                v-model="service.description"
                                maxlength="2000"
                            />
                            <p class="error">
                                {{ service.errors.description }}
                            </p>
                        </div>
                        <div
                            v-if="service.format === 'in_person'"
                            class="field"
                        >
                            <label for="service-location"
                                >Место проведения (если отличается)</label
                            ><input
                                id="service-location"
                                v-model="service.location"
                                maxlength="500"
                            />
                        </div>
                        <p class="muted mb-4">
                            Максимум участников:
                            {{ service.type === "split" ? 2 : 1 }}.
                        </p>
                        <label class="mb-5 font-normal"
                            ><input v-model="service.active" type="checkbox" />
                            Услуга активна</label
                        >
                        <div class="flex gap-3">
                            <button class="btn" :disabled="service.processing">
                                Сохранить услугу</button
                            ><button
                                type="button"
                                class="btn secondary"
                                @click="cancelService()"
                            >
                                Отмена
                            </button>
                        </div>
                    </form>
                </section>
                <section v-if="section === 'access'" class="max-w-3xl">
                    <div class="card mb-5">
                        <h2>Подтверждение личности</h2>
                        <p class="muted mb-4">
                            Для изменения доступа введите текущий пароль или
                            подтвердите связанный Telegram.
                        </p>
                        <label for="current-password">Текущий пароль</label
                        ><input
                            id="current-password"
                            v-model="access.current_password"
                            type="password"
                            autocomplete="current-password"
                        />
                        <p class="error">
                            {{ access.errors.current_password }}
                        </p>
                        <TelegramButton
                            v-if="telegramLinked"
                            class="mt-4"
                            mode="reauth"
                            label="Подтвердить мой Telegram"
                        />
                    </div>
                    <form
                        @submit.prevent="saveAccess('/app/access/password')"
                        class="card mb-5"
                    >
                        <h2>
                            {{
                                hasPassword
                                    ? "Сменить пароль"
                                    : "Установить пароль"
                            }}
                        </h2>
                        <div class="field">
                            <label for="new-password">Новый пароль</label
                            ><input
                                id="new-password"
                                v-model="access.password"
                                type="password"
                                minlength="12"
                                required
                                autocomplete="new-password"
                            />
                            <p class="error">{{ access.errors.password }}</p>
                        </div>
                        <div class="field">
                            <label for="confirm-password"
                                >Повторите пароль</label
                            ><input
                                id="confirm-password"
                                v-model="access.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                        </div>
                        <button class="btn" :disabled="access.processing">
                            Сохранить пароль
                        </button>
                    </form>
                    <form
                        @submit.prevent="saveAccess('/app/access/email')"
                        class="card mb-5"
                    >
                        <h2>Email</h2>
                        <p class="muted mb-4">
                            До подтверждения нового адреса действует прежний.
                        </p>
                        <div class="field">
                            <label for="new-email">Новый email</label
                            ><input
                                id="new-email"
                                v-model="access.email"
                                type="email"
                                required
                            />
                            <p class="error">{{ access.errors.email }}</p>
                        </div>
                        <button class="btn" :disabled="access.processing">
                            Отправить подтверждение
                        </button>
                    </form>
                    <div class="card">
                        <h2>Telegram</h2>
                        <p class="muted mb-4">
                            {{
                                telegramLinked
                                    ? "Аккаунт подключён."
                                    : "Подключите Telegram для быстрого входа."
                            }}
                        </p>
                        <TelegramButton
                            v-if="!telegramLinked"
                            mode="link"
                            label="Подключить Telegram"
                        /><button
                            v-else
                            class="btn danger"
                            :disabled="!hasPassword || access.processing"
                            @click="access.delete('/app/access/telegram')"
                        >
                            Отключить Telegram
                        </button>
                        <p
                            v-if="telegramLinked && !hasPassword"
                            class="muted mt-3"
                        >
                            Сначала установите пароль, чтобы сохранить доступ.
                        </p>
                        <p class="error">
                            {{ (access.errors as any).telegram }}
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
