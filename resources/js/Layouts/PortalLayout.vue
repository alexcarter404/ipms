<script setup>
import Toast from 'primevue/toast';
import { router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { watch } from 'vue';

const page = usePage();
const toast = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.add({ severity: 'success', summary: flash.success, life: 4500 });
        }
        if (flash?.error) {
            toast.add({ severity: 'error', summary: flash.error, life: 6000 });
        }
    },
    { immediate: true, deep: true }
);

const logout = () => router.post(route('portal.logout'));
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <Toast position="top-right" />

        <nav class="border-b border-gray-100 bg-white shadow-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="text-lg font-bold text-indigo-600">IPMS</span>
                    <span class="text-sm text-gray-400">Client Portal</span>
                </div>
                <div v-if="page.props.portal.user" class="flex items-center gap-4 text-sm">
                    <span class="text-gray-600">{{ page.props.portal.user.name }}</span>
                    <button type="button" class="text-indigo-600 hover:underline" @click="logout">
                        Sign out
                    </button>
                </div>
            </div>
        </nav>

        <main>
            <slot />
        </main>
    </div>
</template>
