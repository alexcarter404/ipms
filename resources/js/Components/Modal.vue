<script setup>
import Dialog from 'primevue/dialog';
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: '2xl' },
    closeable: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const width = computed(
    () =>
        ({
            sm: '24rem',
            md: '28rem',
            lg: '32rem',
            xl: '36rem',
            '2xl': '42rem',
        })[props.maxWidth]
);

const onVisibleChange = (visible) => {
    if (!visible && props.closeable) emit('close');
};
</script>

<template>
    <Dialog
        :visible="show"
        modal
        :closable="false"
        :show-header="false"
        :draggable="false"
        :dismissable-mask="closeable"
        :style="{ width, maxWidth: '95vw' }"
        :pt="{ content: { class: '!p-0' } }"
        @update:visible="onVisibleChange"
    >
        <slot />
    </Dialog>
</template>
