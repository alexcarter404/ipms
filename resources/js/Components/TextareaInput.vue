<script setup>
import Textarea from 'primevue/textarea';

const model = defineModel({ type: [String, null], default: '' });

defineProps({
    rows: { type: [String, Number], default: 3 },
});
</script>

<template>
    <Textarea v-model="model" :rows="rows" class="w-full" size="small" />
</template>
