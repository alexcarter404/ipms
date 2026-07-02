<script setup>
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';

const page = usePage();
const toast = useToast();

const announce = (flash) => {
    if (flash?.success) {
        toast.add({ severity: 'success', summary: flash.success, life: 4000 });
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: flash.error, life: 6000 });
    }
};

// The layout remounts on cross-page navigation, so the initial flash
// must be announced AFTER the <Toast> child has mounted and subscribed
// to the service bus — an immediate watcher during setup() loses it.
onMounted(() => announce(page.props.flash));

// Same-page responses (preserveScroll form actions) update the prop in
// place; the flash object is a fresh reference on every response.
watch(
    () => page.props.flash,
    (flash) => announce(flash)
);
</script>

<template>
    <Toast position="top-right" />
</template>
