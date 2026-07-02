<script setup>
import Message from 'primevue/message';
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const dismissed = ref(false);

const flash = computed(() => page.props.flash ?? {});
const message = computed(() => flash.value.success || flash.value.error);
const isError = computed(() => Boolean(flash.value.error));

watch(message, () => (dismissed.value = false));
</script>

<template>
    <div
        v-if="message && !dismissed"
        class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8"
    >
        <Message
            :severity="isError ? 'error' : 'success'"
            closable
            @close="dismissed = true"
        >
            {{ message }}
        </Message>
    </div>
</template>
