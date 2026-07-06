<script setup>
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
});

// Map domain statuses onto PrimeVue Tag severities.
const severities = {
    success: ['granted', 'registered', 'accepted', 'completed', 'paid', 'sent', 'processed', 'grant', 'registration'],
    info: ['filed', 'published', 'in_progress', 'instructed', 'reminder_sent', 'under_examination', 'normal', 'billable', 'issued', 'matched', 'publication', 'renewal_reminder', 'time'],
    warn: ['pending', 'pending_filing', 'upcoming', 'office_action', 'opposed', 'high', 'needs_review'],
    danger: ['critical', 'declined', 'void'],
    secondary: ['draft', 'abandoned', 'lapsed', 'expired', 'closed', 'cancelled', 'waived', 'low', 'billed', 'written_off', 'non_billable', 'dismissed', 'disbursement', 'charge'],
};

const severity = computed(() => {
    for (const [name, statuses] of Object.entries(severities)) {
        if (statuses.includes(props.status)) return name;
    }
    return 'secondary';
});

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
    <Tag :value="text" :severity="severity" class="whitespace-nowrap !text-xs !font-medium" />
</template>
