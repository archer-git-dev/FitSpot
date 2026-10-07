<script setup lang="ts">
import { ref, watch } from "vue";
const props = defineProps<{
    modelValue: number;
    id: string;
    end?: boolean;
    invalid?: boolean;
}>();
const emit = defineEmits<{ "update:modelValue": [value: number] }>();
function format(value: number) {
    return Number.isFinite(value)
        ? `${String(Math.floor(value / 60)).padStart(2, "0")}:${String(value % 60).padStart(2, "0")}`
        : "";
}
const text = ref(format(props.modelValue));
watch(
    () => props.modelValue,
    (value) => {
        if (Number.isFinite(value)) text.value = format(value);
    },
);
function input() {
    const match = /^(\d{2}):(\d{2})$/.exec(text.value);
    const hour = match ? Number(match[1]) : -1,
        minute = match ? Number(match[2]) : -1;
    const valid =
        hour >= 0 &&
        minute >= 0 &&
        minute < 60 &&
        (hour < 24 || (props.end && hour === 24 && minute === 0));
    emit("update:modelValue", valid ? hour * 60 + minute : NaN);
}
</script>
<template>
    <input
        :id="id"
        v-model="text"
        type="text"
        inputmode="numeric"
        placeholder="ЧЧ:ММ"
        maxlength="5"
        autocomplete="off"
        required
        :aria-invalid="invalid || undefined"
        :class="{ 'border-red-600!': invalid }"
        @input="input"
    />
</template>
