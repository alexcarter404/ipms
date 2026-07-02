<script setup>
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
        <div
            class="flex items-center justify-between rounded-md border px-4 py-3 text-sm"
            :class="
                isError
                    ? 'border-red-200 bg-red-50 text-red-800'
                    : 'border-green-200 bg-green-50 text-green-800'
            "
        >
            <span>{{ message }}</span>
            <button
                type="button"
                class="ml-4 font-medium hover:opacity-70"
                @click="dismissed = true"
            >
                &times;
            </button>
        </div>
    </div>
</template>
