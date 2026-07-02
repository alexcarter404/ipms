<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, required: true },
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap gap-1">
        <template v-for="(link, i) in links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="
                    link.active
                        ? 'border-indigo-600 bg-indigo-600 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                "
                v-html="link.label"
            />
            <span
                v-else
                class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-400"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
