<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    emails: Array,
    unmatchedCount: Number,
    matterOptions: Array,
});

const viewing = ref(null);
const assignTo = ref(null);

const open = (email) => {
    viewing.value = email;
    assignTo.value = null;
};

const ingest = () => router.post(route('mailroom.ingest'), {}, { preserveScroll: true });

const assign = () =>
    router.patch(
        route('mailroom.assign', viewing.value.id),
        { matter_id: assignTo.value },
        { preserveScroll: true, onSuccess: () => (viewing.value = null) }
    );
</script>

<template>
    <Head title="Mailroom" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Mailroom</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Inbound email captured from the docketing mailbox. Matched mail files
                        itself (attachments included); the rest waits here for a home.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="unmatchedCount" class="text-sm font-medium text-amber-600">
                        {{ unmatchedCount }} unmatched
                    </span>
                    <PrimaryButton @click="ingest">Check Mailbox Now</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-2.5">Received</th>
                            <th class="px-4 py-2.5">From</th>
                            <th class="px-4 py-2.5">Subject</th>
                            <th class="px-4 py-2.5">Matter</th>
                            <th class="px-4 py-2.5">Attachments</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="email in emails" :key="email.id">
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">
                                {{ email.received_at?.substring(0, 16) ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="font-medium text-gray-800">{{ email.from_name ?? email.from_email }}</span>
                                <div v-if="email.from_name" class="text-xs text-gray-400">{{ email.from_email }}</div>
                            </td>
                            <td class="max-w-md truncate px-4 py-2.5 text-gray-700">{{ email.subject }}</td>
                            <td class="px-4 py-2.5">
                                <Link
                                    v-if="email.matter"
                                    :href="route('matters.show', email.matter.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ email.matter.reference }}
                                </Link>
                                <Tag v-else value="Unmatched" severity="warn" class="!text-xs" />
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">
                                {{ email.attachments.length || '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" class="text-sm text-indigo-600 hover:underline" @click="open(email)">
                                    View →
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!emails.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                The mailroom is empty.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog
            :visible="viewing !== null"
            modal
            :header="viewing?.subject ?? ''"
            :style="{ width: '38rem' }"
            @update:visible="viewing = null"
        >
            <div v-if="viewing" data-testid="email-detail" class="space-y-4 text-sm">
                <div class="text-gray-500">
                    From <span class="font-medium text-gray-800">{{ viewing.from_name ?? viewing.from_email }}</span>
                    <span v-if="viewing.from_name"> &lt;{{ viewing.from_email }}&gt;</span>
                    · {{ viewing.received_at }}
                </div>

                <div class="whitespace-pre-wrap rounded-md bg-gray-50 p-4 text-gray-700">{{ viewing.body }}</div>

                <div v-if="viewing.attachments.length">
                    <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Attachments</h4>
                    <ul class="space-y-1">
                        <li v-for="attachment in viewing.attachments" :key="attachment.name">
                            <a
                                v-if="attachment.document_id"
                                :href="route('documents.download', attachment.document_id)"
                                class="text-indigo-600 hover:underline"
                                >{{ attachment.name }}</a
                            >
                            <span v-else class="text-gray-600">{{ attachment.name }} (files on assignment)</span>
                        </li>
                    </ul>
                </div>

                <div v-if="!viewing.matter" class="rounded-md border border-amber-200 bg-amber-50 p-4">
                    <p class="mb-2 text-xs font-medium text-amber-800">
                        No matter matched — choose where to file this email.
                    </p>
                    <div class="flex gap-2">
                        <Select
                            v-model="assignTo"
                            :options="matterOptions"
                            option-label="label"
                            option-value="value"
                            filter
                            placeholder="Select matter…"
                            class="w-full"
                            size="small"
                        />
                        <PrimaryButton :disabled="!assignTo" @click="assign">File</PrimaryButton>
                    </div>
                </div>
                <div v-else class="text-gray-500">
                    Filed on
                    <Link :href="route('matters.show', viewing.matter.id)" class="font-medium text-indigo-600 hover:underline">
                        {{ viewing.matter.reference }}
                    </Link>
                </div>

                <div class="flex justify-end">
                    <SecondaryButton @click="viewing = null">Close</SecondaryButton>
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
