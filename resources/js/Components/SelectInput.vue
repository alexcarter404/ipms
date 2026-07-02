<script setup>
import Select from 'primevue/select';
import { computed } from 'vue';

const model = defineModel({ type: [String, Number, null], default: '' });

const props = defineProps({
    options: { type: Array, default: () => [] }, // [{value, label}]
    placeholder: { type: String, default: null },
});

// The app treats '' as "nothing selected"; PrimeVue uses null.
const proxy = computed({
    get: () => (model.value === '' ? null : model.value),
    set: (value) => (model.value = value === null ? '' : value),
});
</script>

<template>
    <Select
        v-model="proxy"
        :options="options"
        option-label="label"
        option-value="value"
        :placeholder="placeholder ?? undefined"
        :show-clear="placeholder !== null && proxy !== null"
        size="small"
        class="w-full"
    />
</template>
