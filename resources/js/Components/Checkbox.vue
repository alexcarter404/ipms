<script setup>
import Checkbox from 'primevue/checkbox';
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        required: true,
    },
    value: {
        default: null,
    },
});

const proxyChecked = computed({
    get: () => props.checked,
    set: (val) => emit('update:checked', val),
});
</script>

<template>
    <Checkbox
        v-model="proxyChecked"
        :value="value"
        :binary="typeof checked === 'boolean'"
        size="small"
    />
</template>
