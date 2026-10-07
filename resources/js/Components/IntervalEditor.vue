<script setup lang="ts">
import TimeInput from "./TimeInput.vue";
export type Interval = { start: number; end: number };
const props = defineProps<{
    items: Interval[];
    prefix: string;
    errors: Record<string, string>;
    indices?: number[];
}>();
const emit = defineEmits<{ change: [items: Interval[]] }>();
function key(index: number) {
    return `${props.prefix}.${props.indices?.[index] ?? index}`;
}
function update(index: number, field: "start" | "end", value: number) {
    emit(
        "change",
        props.items.map((item, i) =>
            i === index ? { ...item, [field]: value } : { ...item },
        ),
    );
}
function remove(index: number) {
    emit(
        "change",
        props.items.filter((_, i) => i !== index).map((item) => ({ ...item })),
    );
}
function add() {
    const last = props.items.at(-1);
    const start =
        last && Number.isFinite(last.end) ? Math.min(last.end + 60, 1380) : 540;
    emit("change", [
        ...props.items,
        { start, end: last ? Math.min(start + 60, 1440) : 1080 },
    ]);
}
</script>
<template>
    <div>
        <p v-if="!items.length" class="muted mb-3">Выходной</p>
        <p v-if="errors[prefix]" class="error" role="alert">
            {{ errors[prefix] }}
        </p>
        <div
            v-for="(item, index) in items"
            :key="index"
            class="grid grid-cols-[1fr_1fr_auto] gap-x-3 gap-y-1 mb-4 items-start"
        >
            <div>
                <label :for="`${key(index)}-start`">Начало</label>
                <TimeInput
                    :id="`${key(index)}-start`"
                    :model-value="item.start"
                    :invalid="!!errors[`${key(index)}.start`]"
                    @update:model-value="update(index, 'start', $event)"
                />
                <p v-if="errors[`${key(index)}.start`]" class="error mt-1">
                    {{ errors[`${key(index)}.start`] }}
                </p>
            </div>
            <div>
                <label :for="`${key(index)}-end`">Окончание</label>
                <TimeInput
                    :id="`${key(index)}-end`"
                    :model-value="item.end"
                    end
                    :invalid="!!errors[`${key(index)}.end`]"
                    @update:model-value="update(index, 'end', $event)"
                />
                <p v-if="errors[`${key(index)}.end`]" class="error mt-1">
                    {{ errors[`${key(index)}.end`] }}
                </p>
            </div>
            <button
                type="button"
                class="btn secondary mt-7 px-3!"
                aria-label="Удалить интервал"
                @click="remove(index)"
            >
                ×
            </button>
        </div>
        <button
            type="button"
            class="text-sm font-semibold text-green-800"
            :disabled="items.length >= 8"
            @click="add"
        >
            + Интервал
        </button>
    </div>
</template>
