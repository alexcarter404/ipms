<script setup>
const model = defineModel({ type: [String, Number, null], default: '' });

defineProps({
    options: { type: Array, default: () => [] }, // [{value, label}]
    placeholder: { type: String, default: null },
});
</script>

<template>
    <select
        v-model="model"
        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    >
        <option v-if="placeholder !== null" value="">{{ placeholder }}</option>
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
            {{ opt.label }}
        </option>
    </select>
</template>
