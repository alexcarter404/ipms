<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
});

const palette = {
    // matter statuses
    draft: 'bg-gray-100 text-gray-700',
    pending_filing: 'bg-amber-100 text-amber-800',
    filed: 'bg-blue-100 text-blue-800',
    published: 'bg-sky-100 text-sky-800',
    under_examination: 'bg-indigo-100 text-indigo-800',
    office_action: 'bg-orange-100 text-orange-800',
    accepted: 'bg-teal-100 text-teal-800',
    granted: 'bg-green-100 text-green-800',
    registered: 'bg-green-100 text-green-800',
    opposed: 'bg-rose-100 text-rose-800',
    abandoned: 'bg-gray-200 text-gray-600',
    lapsed: 'bg-gray-200 text-gray-600',
    expired: 'bg-gray-200 text-gray-600',
    closed: 'bg-gray-200 text-gray-600',
    // task statuses
    pending: 'bg-amber-100 text-amber-800',
    in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-600',
    // renewal statuses
    upcoming: 'bg-amber-100 text-amber-800',
    reminder_sent: 'bg-sky-100 text-sky-800',
    instructed: 'bg-indigo-100 text-indigo-800',
    paid: 'bg-green-100 text-green-800',
    waived: 'bg-gray-200 text-gray-600',
    // communications
    sent: 'bg-green-100 text-green-800',
    // priorities
    low: 'bg-gray-100 text-gray-600',
    normal: 'bg-blue-50 text-blue-700',
    high: 'bg-orange-100 text-orange-800',
    critical: 'bg-red-100 text-red-800',
};

const classes = computed(
    () => palette[props.status] ?? 'bg-gray-100 text-gray-700'
);

const text = computed(
    () =>
        props.label ??
        props.status
            .split('_')
            .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
            .join(' ')
);
</script>

<template>
    <span
        class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="classes"
    >
        {{ text }}
    </span>
</template>
