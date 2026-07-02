<script setup>
const model = defineModel({ type: [String, null], default: '' });

defineProps({
    rows: { type: [String, Number], default: 3 },
});
</script>

<template>
    <textarea
        v-model="model"
        :rows="rows"
        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    />
</template>
