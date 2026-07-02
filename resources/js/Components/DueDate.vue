<script setup>
import { computed } from 'vue';

const props = defineProps({
    date: { type: String, default: null },
    // color the date red/amber when it is (nearly) due
    highlight: { type: Boolean, default: true },
});

const parsed = computed(() => (props.date ? new Date(props.date) : null));

const formatted = computed(() =>
    parsed.value
        ? parsed.value.toLocaleDateString(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—'
);

const daysAway = computed(() => {
    if (!parsed.value) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((parsed.value - today) / 86400000);
});

const classes = computed(() => {
    if (!props.highlight || daysAway.value === null) return 'text-gray-700';
    if (daysAway.value < 0) return 'font-semibold text-red-600';
    if (daysAway.value <= 30) return 'font-medium text-amber-600';
    return 'text-gray-700';
});
</script>

<template>
    <span :class="classes" :title="daysAway !== null ? `${daysAway} day(s)` : ''">
        {{ formatted }}
    </span>
</template>
