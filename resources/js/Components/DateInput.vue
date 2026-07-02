<script setup>
import DatePicker from 'primevue/datepicker';
import { computed } from 'vue';

// The app stores dates as 'YYYY-MM-DD' strings; DatePicker works with
// Date objects. Convert in local time so days never shift.
const model = defineModel({ type: [String, null], default: '' });

const proxy = computed({
    get() {
        if (!model.value) return null;
        const [y, m, d] = model.value.substring(0, 10).split('-').map(Number);
        return new Date(y, m - 1, d);
    },
    set(value) {
        model.value = value
            ? `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`
            : '';
    },
});
</script>

<template>
    <DatePicker
        v-model="proxy"
        date-format="yy-mm-dd"
        show-icon
        icon-display="input"
        show-button-bar
        size="small"
        class="w-full"
    />
</template>
