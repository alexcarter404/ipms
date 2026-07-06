<script setup>
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Tag from 'primevue/tag';
import { router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
    audits: { type: Array, default: () => [] },
    // Shown when the timeline is empty, so each screen can word it
    emptyText: { type: String, default: 'No audited activity yet.' },
});

const eventSeverity = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    restored: 'warn',
};

const confirm = useConfirm();

// Version-history semantics: each entry captures the state it left the
// record in; restoring applies exactly those values.
const restore = (audit) =>
    confirm.require({
        message:
            'Restore the record to the values captured by this entry? The restore is audited too.',
        header: 'Restore state',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true, size: 'small' },
        acceptProps: { label: 'Restore', size: 'small' },
        accept: () =>
            router.post(route('audits.transition', audit.id), {}, { preserveScroll: true }),
    });
</script>

<template>
    <div data-testid="audit-trail">
        <p v-if="!audits.length" class="py-8 text-center text-sm text-gray-500">
            {{ emptyText }}
        </p>

        <ol v-else class="relative ml-3 space-y-6 border-l border-gray-200 pb-1">
            <li v-for="audit in audits" :key="audit.id" class="relative pl-6">
                <span
                    class="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full"
                    :class="{
                        'bg-green-500': audit.event === 'created',
                        'bg-blue-500': audit.event === 'updated',
                        'bg-red-500': audit.event === 'deleted',
                        'bg-amber-500': audit.event === 'restored',
                    }"
                />

                <div class="flex flex-wrap items-center gap-2">
                    <Tag
                        :value="audit.event"
                        :severity="eventSeverity[audit.event] ?? 'secondary'"
                        class="!text-xs !font-medium capitalize"
                    />
                    <span class="text-sm font-medium text-gray-800">
                        {{ audit.subject_type }}
                        <span v-if="audit.subject_label" class="font-normal text-gray-600">
                            — {{ audit.subject_label }}
                        </span>
                    </span>
                    <span class="text-xs text-gray-400" :title="audit.at">
                        {{ audit.user }} · {{ audit.at_human }}
                    </span>
                </div>

                <dl v-if="audit.changes.length" class="mt-2 space-y-1">
                    <div
                        v-for="change in audit.changes"
                        :key="change.field"
                        class="flex flex-wrap items-baseline gap-x-2 text-sm"
                    >
                        <dt class="font-medium capitalize text-gray-500">{{ change.field }}</dt>
                        <dd class="text-gray-700">
                            <template v-if="audit.event === 'updated'">
                                <span class="text-gray-400 line-through">{{ change.old ?? '—' }}</span>
                                <span class="mx-1 text-gray-400">to</span>
                            </template>
                            <span>{{ change.new ?? '—' }}</span>
                        </dd>
                    </div>
                </dl>

                <div v-if="audit.can_transition" class="mt-2">
                    <SecondaryButton class="!px-2 !py-1 !text-xs" @click="restore(audit)">
                        ⟲ Restore this state
                    </SecondaryButton>
                </div>
            </li>
        </ol>
    </div>
</template>
