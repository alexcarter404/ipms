<script setup>
import InputText from 'primevue/inputtext';
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: [String, Number, null],
    required: true,
});

const input = ref(null);

const focus = () => input.value?.$el?.focus();

onMounted(() => {
    if (input.value?.$el?.hasAttribute('autofocus')) {
        focus();
    }
});

defineExpose({ focus });
</script>

<template>
    <InputText ref="input" v-model="model" size="small" />
</template>
