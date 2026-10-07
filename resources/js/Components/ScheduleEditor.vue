<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import IntervalEditor, { type Interval } from "./IntervalEditor.vue";
import { useDirty } from "../composables/dirty";
type Weekly = Interval & { day: number };
type Day = {
    date: string;
    day: number;
    override: boolean;
    intervals: Interval[];
};
const props = defineProps<{
    week: Weekly[];
    calendar: Day[];
    today: string;
    timezone: string;
}>();
const weekdays = [
    "Понедельник",
    "Вторник",
    "Среда",
    "Четверг",
    "Пятница",
    "Суббота",
    "Воскресенье",
];
const mode = ref<"week" | "calendar">("week"),
    view = ref<"week" | "month">("month");
const anchor = ref(props.today),
    selected = ref(props.today),
    sourceDay = ref(1),
    weekTargets = ref<number[]>([]);
const week = useForm({ intervals: props.week.map((i) => ({ ...i })) });
const dates = useForm({
    days: [] as { date: string; intervals: Interval[] }[],
});
const banner = ref<HTMLElement | null>(null),
    busy = ref(false),
    localError = ref("");
const targetFrom = ref(props.today),
    targetTo = ref(props.today);
const copyMode = ref<"day" | "week">("day");
function date(value: string) {
    return new Date(`${value}T12:00:00Z`);
}
function iso(value: Date) {
    return value.toISOString().slice(0, 10);
}
function shift(value: string, count: number) {
    const d = date(value);
    d.setUTCDate(d.getUTCDate() + count);
    return iso(d);
}
function dow(value: string) {
    return date(value).getUTCDay() || 7;
}
function label(value: string) {
    return date(value).toLocaleDateString("ru-RU", {
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "UTC",
    });
}
function minutes(value: number) {
    return `${String(Math.floor(value / 60)).padStart(2, "0")}:${String(value % 60).padStart(2, "0")}`;
}
function clone(items: Interval[]) {
    return items.map((i) => ({ start: i.start, end: i.end }));
}
const range = computed(() => {
    const a = date(anchor.value);
    const start =
        view.value === "week"
            ? shift(anchor.value, 1 - dow(anchor.value))
            : shift(
                  iso(
                      new Date(
                          Date.UTC(a.getUTCFullYear(), a.getUTCMonth(), 1, 12),
                      ),
                  ),
                  1 -
                      dow(
                          iso(
                              new Date(
                                  Date.UTC(
                                      a.getUTCFullYear(),
                                      a.getUTCMonth(),
                                      1,
                                      12,
                                  ),
                              ),
                          ),
                      ),
              );
    return { from: start, to: shift(start, view.value === "week" ? 6 : 41) };
});
const cells = computed(() =>
    Array.from({ length: view.value === "week" ? 7 : 42 }, (_, i) =>
        shift(range.value.from, i),
    ),
);
const periodLabel = computed(() =>
    view.value === "month"
        ? date(anchor.value).toLocaleDateString("ru-RU", {
              month: "long",
              year: "numeric",
              timeZone: "UTC",
          })
        : `${label(range.value.from)} — ${label(range.value.to)}`,
);
const selectedIndex = computed(() =>
    dates.days.findIndex((d) => d.date === selected.value),
);
const selectedItems = computed(() => effective(selected.value));
const selectedPrefix = computed(
    () => `days.${Math.max(0, selectedIndex.value)}.intervals`,
);
function effective(value: string): Interval[] {
    const draft = dates.days.find((d) => d.date === value);
    if (draft) return draft.intervals;
    const existing = props.calendar?.find((d) => d.date === value);
    if (existing) return existing.intervals;
    return week.intervals.filter((i) => i.day === dow(value));
}
function override(value: string) {
    return !!props.calendar?.find((d) => d.date === value)?.override;
}
function stage(value: string, items: Interval[], force = false) {
    dates.clearErrors();
    localError.value = "";
    const next = dates.days.filter((d) => d.date !== value);
    const saved = props.calendar?.find((day) => day.date === value)?.intervals;
    if (!force && saved && JSON.stringify(items) === JSON.stringify(saved)) {
        dates.days = next;
        return;
    }
    if (next.length >= 31) {
        localError.value =
            "За одно сохранение можно изменить максимум 31 дату. Сохраните текущие изменения.";
        return;
    }
    dates.days = [...next, { date: value, intervals: clone(items) }];
}
function updateWeek(day: number, items: Interval[]) {
    week.clearErrors();
    week.intervals = [
        ...week.intervals.filter((i) => i.day !== day),
        ...items.map((i) => ({ ...i, day })),
    ];
}
function copyWeekday() {
    const items = week.intervals.filter((i) => i.day === sourceDay.value);
    for (const day of weekTargets.value) updateWeek(day, clone(items));
    weekTargets.value = [];
}
function guard() {
    return (
        !(week.isDirty || dates.isDirty) ||
        confirm("Изменения не сохранены. Отбросить изменения?")
    );
}
function switchMode(value: "week" | "calendar") {
    if (value === mode.value || !guard()) return;
    week.reset();
    week.clearErrors();
    dates.reset();
    dates.clearErrors();
    localError.value = "";
    mode.value = value;
    if (value === "calendar") loadRange();
}
function loadRange() {
    busy.value = true;
    router.get("/app/schedule", range.value, {
        only: ["calendar"],
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
    });
}
function move(count: number) {
    if (!guard()) return;
    dates.reset();
    dates.clearErrors();
    localError.value = "";
    if (view.value === "week") anchor.value = shift(anchor.value, count * 7);
    else {
        const d = date(anchor.value);
        anchor.value = iso(
            new Date(
                Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + count, 1, 12),
            ),
        );
    }
    selected.value = range.value.from;
    loadRange();
}
function changeView(value: "week" | "month") {
    if (view.value === value || !guard()) return;
    dates.reset();
    dates.clearErrors();
    view.value = value;
    loadRange();
}
async function errors() {
    await nextTick();
    banner.value?.focus();
    banner.value?.scrollIntoView({ block: "nearest", behavior: "smooth" });
}
function saveWeek() {
    week.put("/app/schedule", {
        preserveScroll: true,
        onSuccess: () => week.defaults(),
        onError: errors,
    });
}
function saveDates() {
    dates.put("/app/schedule/dates", {
        preserveScroll: true,
        onSuccess: () => {
            dates.days = [];
            dates.defaults();
            if (
                selected.value < range.value.from ||
                selected.value > range.value.to
            ) {
                anchor.value = selected.value;
                loadRange();
            }
        },
        onError: errors,
    });
}
function resetDate() {
    if (!guard()) return;
    if (
        !confirm(
            `${label(selected.value)}: вернуть расписание к недельному шаблону?`,
        )
    )
        return;
    dates.reset();
    dates.clearErrors();
    busy.value = true;
    router.delete(`/app/schedule/dates/${selected.value}`, {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
    });
}
async function copyDates() {
    localError.value = "";
    if (
        !/^\d{4}-\d{2}-\d{2}$/.test(targetFrom.value) ||
        !/^\d{4}-\d{2}-\d{2}$/.test(targetTo.value) ||
        !Number.isFinite(date(targetFrom.value).getTime()) ||
        !Number.isFinite(date(targetTo.value).getTime())
    ) {
        localError.value = "Выберите корректные даты периода.";
        return;
    }
    const count =
        Math.round(
            (date(targetTo.value).getTime() -
                date(targetFrom.value).getTime()) /
                86400000,
        ) + 1;
    if (count < 1 || count > 31) {
        localError.value = "Выберите период от 1 до 31 дня.";
        return;
    }
    const targets = Array.from({ length: count }, (_, i) =>
        shift(targetFrom.value, i),
    );
    if (new Set([...dates.days.map((d) => d.date), ...targets]).size > 31) {
        localError.value =
            "За одно сохранение можно изменить максимум 31 дату.";
        return;
    }
    busy.value = true;
    try {
        const sourceWeek = shift(selected.value, 1 - dow(selected.value));
        const read = async (from: string, to: string): Promise<Day[]> => {
            const response = await fetch(
                `/app/schedule/calendar?from=${from}&to=${to}`,
                { headers: { Accept: "application/json" } },
            );
            if (!response.ok)
                throw new Error(
                    "Не удалось проверить расписание дат. Повторите попытку.",
                );
            return (await response.json()).days;
        };
        const [targetDays, sourceDays] = await Promise.all([
            read(targetFrom.value, targetTo.value),
            read(sourceWeek, shift(sourceWeek, 6)),
        ]);
        const affected = targets.filter(
            (d) =>
                targetDays.find((item) => item.date === d)?.override ||
                dates.days.some((item) => item.date === d),
        );
        if (
            affected.length &&
            !confirm(
                `Заменить индивидуальные настройки этих дат?\n${affected.map(label).join("\n")}`,
            )
        )
            return;
        const source = (value: string) =>
            clone(
                dates.days.find((d) => d.date === value)?.intervals ??
                    sourceDays.find((d) => d.date === value)?.intervals ??
                    [],
            );
        const daySource = source(selected.value);
        const weekSource = Array.from({ length: 7 }, (_, i) =>
            source(shift(sourceWeek, i)),
        );
        for (const d of targets)
            stage(
                d,
                copyMode.value === "day" ? daySource : weekSource[dow(d) - 1],
                true,
            );
    } catch (error) {
        localError.value =
            error instanceof Error
                ? error.message
                : "Не удалось скопировать расписание.";
    } finally {
        busy.value = false;
    }
}
useDirty(() => week.isDirty || dates.isDirty);
watch(
    () => props.week,
    (items) => {
        if (!week.isDirty) {
            week.intervals = items.map((i) => ({ ...i }));
            week.defaults();
        }
    },
);
</script>
<template>
    <section>
        <p class="muted mb-4">
            Часовой пояс: {{ timezone }}. Даты без индивидуальных настроек
            используют недельный шаблон.
        </p>
        <div
            class="flex flex-wrap gap-2 mb-5"
            role="group"
            aria-label="Вид расписания"
        >
            <button
                type="button"
                :class="['btn', mode !== 'week' && 'secondary']"
                :aria-pressed="mode === 'week'"
                @click="switchMode('week')"
            >
                Недельный шаблон
            </button>
            <button
                type="button"
                :class="['btn', mode !== 'calendar' && 'secondary']"
                :aria-pressed="mode === 'calendar'"
                @click="switchMode('calendar')"
            >
                Календарь
            </button>
        </div>
        <div
            v-if="
                Object.keys(week.errors).length ||
                Object.keys(dates.errors).length ||
                localError
            "
            ref="banner"
            role="alert"
            tabindex="-1"
            class="rounded-xl border border-red-300 bg-red-50 text-red-900 p-4 mb-5"
        >
            <p class="font-semibold">
                График не сохранён — исправьте отмеченные интервалы.
            </p>
            <p v-if="localError">{{ localError }}</p>
            <p
                v-for="message in [
                    ...new Set([
                        ...Object.values(week.errors),
                        ...Object.values(dates.errors),
                    ]),
                ]"
            >
                {{ message }}
            </p>
        </div>
        <form v-if="mode === 'week'" @submit.prevent="saveWeek">
            <div v-for="(day, index) in weekdays" :key="day" class="card mb-4">
                <h2>{{ day }}</h2>
                <IntervalEditor
                    :items="week.intervals.filter((i) => i.day === index + 1)"
                    :indices="
                        week.intervals
                            .map((item, i) => ({ item, i }))
                            .filter((entry) => entry.item.day === index + 1)
                            .map((entry) => entry.i)
                    "
                    prefix="intervals"
                    :errors="week.errors"
                    @change="updateWeek(index + 1, $event)"
                />
            </div>
            <div class="card mb-5">
                <h2>Скопировать график дня</h2>
                <label for="copy-source">Из дня</label
                ><select id="copy-source" v-model="sourceDay">
                    <option v-for="(day, index) in weekdays" :value="index + 1">
                        {{ day }}
                    </option>
                </select>
                <div class="flex flex-wrap gap-3 my-4">
                    <label v-for="(day, index) in weekdays" class="font-normal"
                        ><input
                            v-model="weekTargets"
                            type="checkbox"
                            :value="index + 1"
                            :disabled="sourceDay === index + 1"
                        />
                        {{ day }}</label
                    >
                </div>
                <button
                    class="btn secondary"
                    type="button"
                    :disabled="!weekTargets.length"
                    @click="copyWeekday"
                >
                    Скопировать
                </button>
            </div>
            <button class="btn" :disabled="week.processing">
                Сохранить график
            </button>
        </form>
        <div v-else>
            <div class="flex flex-wrap gap-2 justify-between items-center mb-4">
                <div class="flex gap-2">
                    <button
                        class="btn secondary"
                        type="button"
                        aria-label="Предыдущий период"
                        :disabled="busy"
                        @click="move(-1)"
                    >
                        ←</button
                    ><button
                        class="btn secondary"
                        type="button"
                        aria-label="Следующий период"
                        :disabled="busy"
                        @click="move(1)"
                    >
                        →
                    </button>
                </div>
                <p class="font-semibold capitalize">{{ periodLabel }}</p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        :class="['btn', view !== 'week' && 'secondary']"
                        @click="changeView('week')"
                    >
                        Неделя</button
                    ><button
                        type="button"
                        :class="['btn', view !== 'month' && 'secondary']"
                        @click="changeView('month')"
                    >
                        Месяц
                    </button>
                </div>
            </div>
            <div
                class="grid grid-cols-7 gap-1 mb-1 text-center text-xs text-slate-600"
            >
                <span
                    v-for="day in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']"
                    >{{ day }}</span
                >
            </div>
            <div
                class="grid grid-cols-7 gap-1 mb-5"
                aria-label="Календарь расписания"
            >
                <button
                    v-for="value in cells"
                    :key="value"
                    type="button"
                    :data-date="value"
                    :aria-label="label(value)"
                    :aria-pressed="selected === value"
                    :disabled="busy"
                    @click="selected = value"
                    :class="[
                        'min-w-0 rounded-lg border p-1 sm:p-2 min-h-16 sm:min-h-24 text-left',
                        selected === value
                            ? 'border-green-700 bg-green-50'
                            : 'border-slate-200 bg-white',
                        value === today && 'font-bold',
                    ]"
                >
                    <span class="block text-sm">{{
                        date(value).getUTCDate()
                    }}</span>
                    <span
                        class="hidden sm:block text-[10px] leading-tight mt-1"
                        >{{
                            effective(value).length
                                ? effective(value)
                                      .map(
                                          (i) =>
                                              `${minutes(i.start)}–${minutes(i.end)}`,
                                      )
                                      .join(", ")
                                : "Выходной"
                        }}</span
                    >
                    <span class="block text-[10px] mt-1 text-green-800">{{
                        dates.days.some((d) => d.date === value)
                            ? "●"
                            : override(value)
                              ? "★"
                              : effective(value).length
                                ? "•"
                                : "—"
                    }}</span>
                </button>
            </div>
            <p class="muted text-xs mb-5">
                ★ Индивидуальный день · ● Несохранённые изменения · • Рабочий
                день
            </p>
            <form @submit.prevent="saveDates">
                <div class="card mb-5">
                    <h2>{{ label(selected) }}</h2>
                    <p class="muted mb-4">
                        {{
                            override(selected)
                                ? "Индивидуальное расписание"
                                : "По недельному шаблону"
                        }}
                    </p>
                    <IntervalEditor
                        :items="selectedItems"
                        :prefix="selectedPrefix"
                        :errors="dates.errors"
                        @change="stage(selected, $event)"
                    />
                    <div class="flex flex-wrap gap-3 mt-5">
                        <button
                            type="button"
                            class="btn secondary"
                            @click="stage(selected, [], true)"
                        >
                            Сделать выходным
                        </button>
                        <button
                            v-if="override(selected)"
                            type="button"
                            class="btn secondary"
                            :disabled="busy"
                            @click="resetDate"
                        >
                            Вернуть к шаблону
                        </button>
                    </div>
                </div>
                <div class="card mb-5">
                    <h2>Скопировать на даты</h2>
                    <p class="muted mb-3">
                        Источник — выбранный день или неделя, в которую он
                        входит.
                    </p>
                    <label for="copy-calendar-kind">Что копировать</label
                    ><select id="copy-calendar-kind" v-model="copyMode">
                        <option value="day">Выбранный день</option>
                        <option value="week">Выбранную неделю</option>
                    </select>
                    <div class="grid sm:grid-cols-2 gap-x-4 mt-3">
                        <div>
                            <label for="copy-from">С даты</label
                            ><input
                                id="copy-from"
                                v-model="targetFrom"
                                type="date"
                                required
                            />
                        </div>
                        <div>
                            <label for="copy-to">По дату включительно</label
                            ><input
                                id="copy-to"
                                v-model="targetTo"
                                type="date"
                                required
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn secondary mt-3"
                        :disabled="busy || dates.processing"
                        @click="copyDates"
                    >
                        Подготовить копию
                    </button>
                </div>
                <div v-if="dates.days.length" class="card mb-5">
                    <h2>Изменённые даты: {{ dates.days.length }}</h2>
                    <button
                        v-for="day in dates.days"
                        type="button"
                        class="block text-sm text-green-800 underline mb-2"
                        @click="selected = day.date"
                    >
                        {{ label(day.date) }}
                    </button>
                </div>
                <button
                    class="btn"
                    :disabled="dates.processing || busy || !dates.days.length"
                >
                    Сохранить выбранные даты
                </button>
            </form>
        </div>
    </section>
</template>
