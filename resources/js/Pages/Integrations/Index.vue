<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    messages: Object,
    filters: Object,
    statuses: Array,
    eventTypes: Array,
    offices: Array,
    counts: Object,
    matterOptions: Array,
    submissions: Array,
    submissionTypes: Array,
    openTasks: Object,
    registerChecks: { type: Array, default: () => [] },
});

const fieldLabel = (field) => field.replaceAll('_', ' ');

const runReconciliation = () =>
    router.post(route('integrations.reconcile'), {}, { preserveScroll: true });

const acceptCheck = (check) =>
    router.post(route('register-checks.accept', check.id), {}, { preserveScroll: true });

const dismissCheck = (check) =>
    router.post(route('register-checks.dismiss', check.id), {}, { preserveScroll: true });

const form = reactive({
    status: props.filters.status ?? '',
    office: props.filters.office ?? '',
});

const pruned = () =>
    Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('integrations.index'), pruned(), { preserveState: true, replace: true });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const onPage = (event) => {
    router.get(
        route('integrations.index'),
        { ...pruned(), page: event.page + 1 },
        { preserveState: true, replace: true }
    );
};

const poll = () => router.post(route('integrations.poll'), {}, { preserveScroll: true });

// --- detail / review modal ---
const selected = ref(null);

const assignForm = useForm({ matter_id: '' });

const openMessage = (message) => {
    selected.value = message;
    assignForm.matter_id = message.matter?.id ?? '';
    assignForm.clearErrors();
};

const assign = () =>
    assignForm.patch(route('office-messages.assign', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => (selected.value = null),
    });

const processMessage = (message) =>
    router.post(route('office-messages.process', message.id), {}, {
        preserveScroll: true,
        onSuccess: () => (selected.value = null),
    });

const dismiss = (message) =>
    router.post(route('office-messages.dismiss', message.id), {}, {
        preserveScroll: true,
        onSuccess: () => (selected.value = null),
    });

// --- outbound submissions ---
const showNewSubmission = ref(false);
const viewingSubmission = ref(null);

const submissionForm = useForm({
    office: '',
    matter_id: '',
    submission_type: 'filing',
    task_id: '',
    notes: '',
});

const taskOptions = () => props.openTasks[submissionForm.matter_id] ?? [];

const createSubmission = () =>
    submissionForm
        .transform((d) => ({ ...d, task_id: d.task_id || null, notes: d.notes || null }))
        .post(route('office-submissions.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showNewSubmission.value = false;
                submissionForm.reset();
            },
        });

const submitSubmission = (submission) =>
    router.post(route('office-submissions.submit', submission.id), {}, {
        preserveScroll: true,
        onSuccess: () => (viewingSubmission.value = null),
    });

const deleteSubmission = (submission) =>
    router.delete(route('office-submissions.destroy', submission.id), { preserveScroll: true });

const shortDate = (value) =>
    value
        ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';
</script>

<template>
    <Head title="Integrations" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Integrations — Office Exchange
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Inbound communications from IP offices. Matched messages
                        auto-complete actions, raise official-fee charges and
                        draft client comms; anything ambiguous waits here for review.
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right text-sm">
                        <span class="font-semibold" :class="counts.needs_review ? 'text-amber-600' : 'text-gray-700'">
                            {{ counts.needs_review }} to review
                        </span>
                        <span class="text-gray-400"> · </span>
                        <span class="text-gray-600">{{ counts.processed }} processed</span>
                    </div>
                    <PrimaryButton @click="poll">Poll offices now</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Connections -->
            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div
                    v-for="office in offices"
                    :key="office.value"
                    class="rounded-lg bg-white p-3 text-sm shadow-sm"
                >
                    <div class="font-medium text-gray-800">{{ office.label }}</div>
                    <div class="text-xs text-gray-500">connector: {{ office.driver }}</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <SelectInput v-model="form.status" :options="statuses" placeholder="All statuses" />
                <SelectInput v-model="form.office" :options="offices" placeholder="All offices" />
            </div>

            <DataTable
                :value="messages.data"
                lazy
                paginator
                :rows="messages.per_page"
                :total-records="messages.total"
                :first="(messages.current_page - 1) * messages.per_page"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
                @page="onPage"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">
                        No office messages — drop exchange files into the inbox
                        and poll, or wait for the hourly sync.
                    </p>
                </template>

                <Column header="Received">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-600">{{ shortDate(data.received_at) }}</span>
                    </template>
                </Column>
                <Column header="Office">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-700">{{ data.office_name }}</span>
                    </template>
                </Column>
                <Column header="Event">
                    <template #body="{ data }">
                        <StatusBadge :status="data.event_type" :label="data.event_label" />
                    </template>
                </Column>
                <Column header="Number">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-600">
                            {{ data.application_no ?? data.registration_no ?? '—' }}
                        </span>
                    </template>
                </Column>
                <Column header="Matter">
                    <template #body="{ data }">
                        <Link
                            v-if="data.matter"
                            :href="route('matters.show', data.matter.id)"
                            class="whitespace-nowrap font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.matter.reference }}
                        </Link>
                        <span v-else class="text-amber-600">Unmatched</span>
                    </template>
                </Column>
                <Column header="Summary">
                    <template #body="{ data }">
                        <span class="block max-w-xs truncate text-gray-600">{{ data.summary ?? '—' }}</span>
                    </template>
                </Column>
                <Column header="Status">
                    <template #body="{ data }">
                        <StatusBadge :status="data.status" />
                    </template>
                </Column>
                <Column>
                    <template #body="{ data }">
                        <button class="whitespace-nowrap text-sm font-medium text-indigo-600 hover:underline" @click="openMessage(data)">
                            Review →
                        </button>
                    </template>
                </Column>
            </DataTable>

            <!-- Outbound submissions -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm" data-testid="submissions">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">Outbound Submissions</h3>
                        <p class="text-xs text-gray-500">
                            Filings, responses and payments pushed to the offices —
                            acknowledgement completes the linked docket task.
                        </p>
                    </div>
                    <SecondaryButton @click="showNewSubmission = true">New Submission</SecondaryButton>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Created</th>
                            <th class="px-4 py-2.5">Office</th>
                            <th class="px-4 py-2.5">Type</th>
                            <th class="px-4 py-2.5">Matter</th>
                            <th class="px-4 py-2.5">Discharges task</th>
                            <th class="px-4 py-2.5">Office ref</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="submission in submissions" :key="submission.id">
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ shortDate(submission.created_at) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-700">{{ submission.office_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-700">{{ submission.type_label }}</td>
                            <td class="px-4 py-2.5">
                                <Link :href="route('matters.show', submission.matter.id)" class="whitespace-nowrap font-medium text-indigo-600 hover:underline">
                                    {{ submission.matter.reference }}
                                </Link>
                            </td>
                            <td class="max-w-[12rem] truncate px-4 py-2.5 text-gray-600">{{ submission.task?.title ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ submission.external_ref ?? '—' }}</td>
                            <td class="px-4 py-2.5"><StatusBadge :status="submission.status" /></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-xs">
                                <button class="text-indigo-600 hover:underline" @click="viewingSubmission = submission">View</button>
                                <button
                                    v-if="['draft', 'failed'].includes(submission.status)"
                                    class="ml-2 font-medium text-indigo-600 hover:underline"
                                    @click="submitSubmission(submission)"
                                >
                                    Submit
                                </button>
                                <button
                                    v-if="submission.status === 'draft'"
                                    class="ml-2 text-red-600 hover:underline"
                                    @click="deleteSubmission(submission)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!submissions.length">
                            <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                No outbound submissions yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Register reconciliation -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm" data-testid="register-checks">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">Register Reconciliation</h3>
                        <p class="text-sm text-gray-500">
                            Our docket vs the office record — drift means the register says
                            something we don't.
                        </p>
                    </div>
                    <SecondaryButton @click="runReconciliation">Reconcile Now</SecondaryButton>
                </div>
                <div v-if="!registerChecks.length" class="px-4 py-6 text-center text-sm text-gray-500">
                    No open register checks — the docket matches the offices.
                </div>
                <div v-else class="divide-y">
                    <div v-for="check in registerChecks" :key="check.id" class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="text-sm">
                                <Link
                                    v-if="check.matter"
                                    :href="route('matters.show', check.matter.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ check.matter.reference }}
                                </Link>
                                <span class="ml-2 uppercase text-gray-400">{{ check.office }}</span>
                                <StatusBadge
                                    :status="check.status"
                                    :label="check.status === 'not_found' ? 'Not on register' : 'Drift'"
                                    class="ml-2"
                                />
                            </div>
                            <div class="flex gap-2 text-xs">
                                <button
                                    v-if="check.status === 'drift'"
                                    type="button"
                                    class="text-indigo-600 hover:underline"
                                    @click="acceptCheck(check)"
                                >
                                    Accept office values
                                </button>
                                <button type="button" class="text-gray-500 hover:underline" @click="dismissCheck(check)">
                                    Dismiss
                                </button>
                            </div>
                        </div>
                        <dl v-if="check.differences.length" class="mt-2 space-y-1 text-sm">
                            <div v-for="difference in check.differences" :key="difference.field" class="flex flex-wrap gap-x-2">
                                <dt class="font-medium capitalize text-gray-500">{{ fieldLabel(difference.field) }}</dt>
                                <dd class="text-gray-700">
                                    <span class="text-gray-400 line-through">{{ difference.ours ?? '—' }}</span>
                                    <span class="mx-1 text-gray-400">vs register</span>
                                    <span class="font-medium">{{ difference.theirs ?? '—' }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- New submission modal -->
        <Modal :show="showNewSubmission" @close="showNewSubmission = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">New Office Submission</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Office" />
                        <SelectInput v-model="submissionForm.office" :options="offices" placeholder="Select…" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Type" />
                        <SelectInput v-model="submissionForm.submission_type" :options="submissionTypes" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Matter" />
                        <SelectInput
                            v-model="submissionForm.matter_id"
                            :options="matterOptions.map((m) => ({ value: m.id, label: `${m.reference} — ${m.title}` }))"
                            placeholder="Select…"
                            class="mt-1"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Discharges task (optional)" />
                        <SelectInput
                            v-model="submissionForm.task_id"
                            :options="taskOptions()"
                            placeholder="—"
                            class="mt-1"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Completed automatically when the office acknowledges the submission.
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Notes" />
                        <TextareaInput v-model="submissionForm.notes" class="mt-1" rows="2" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showNewSubmission = false">Cancel</SecondaryButton>
                    <PrimaryButton
                        :disabled="submissionForm.processing || !submissionForm.office || !submissionForm.matter_id"
                        @click="createSubmission"
                    >
                        Create Draft
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Submission detail modal -->
        <Modal :show="viewingSubmission !== null" max-width="2xl" @close="viewingSubmission = null">
            <div v-if="viewingSubmission" class="space-y-4 p-6" data-testid="submission-detail">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ viewingSubmission.type_label }} — {{ viewingSubmission.office_name }}
                    </h3>
                    <StatusBadge :status="viewingSubmission.status" />
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Created by</dt><dd class="text-gray-700">{{ viewingSubmission.created_by }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Submitted</dt><dd class="text-gray-700">{{ viewingSubmission.submitted_at ? shortDate(viewingSubmission.submitted_at) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Acknowledged</dt><dd class="text-gray-700">{{ viewingSubmission.acknowledged_at ? shortDate(viewingSubmission.acknowledged_at) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Office ref</dt><dd class="text-gray-700">{{ viewingSubmission.external_ref ?? '—' }}</dd></div>
                </dl>
                <div v-if="viewingSubmission.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {{ viewingSubmission.error }}
                </div>
                <div class="text-sm">
                    <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Package</h4>
                    <pre class="max-h-56 overflow-auto rounded-md bg-gray-50 p-3 text-xs text-gray-700">{{ JSON.stringify(viewingSubmission.payload, null, 2) }}</pre>
                </div>
                <div v-if="['draft', 'failed'].includes(viewingSubmission.status)" class="flex justify-end gap-3">
                    <SecondaryButton @click="viewingSubmission = null">Close</SecondaryButton>
                    <PrimaryButton @click="submitSubmission(viewingSubmission)">Submit to Office</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Detail / review modal -->
        <Modal :show="selected !== null" max-width="2xl" @close="selected = null">
            <div v-if="selected" class="space-y-4 p-6" data-testid="message-detail">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ selected.office_name }} — {{ selected.event_label }}
                    </h3>
                    <StatusBadge :status="selected.status" />
                </div>

                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Received</dt><dd class="text-gray-700">{{ shortDate(selected.received_at) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Event date</dt><dd class="text-gray-700">{{ shortDate(selected.event_date) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Application no</dt><dd class="text-gray-700">{{ selected.application_no ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Registration no</dt><dd class="text-gray-700">{{ selected.registration_no ?? '—' }}</dd></div>
                </dl>

                <p v-if="selected.summary" class="rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                    {{ selected.summary }}
                </p>

                <div v-if="selected.payload" class="text-sm">
                    <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Payload</h4>
                    <pre class="max-h-40 overflow-auto rounded-md bg-gray-50 p-3 text-xs text-gray-700">{{ JSON.stringify(selected.payload, null, 2) }}</pre>
                </div>

                <div v-if="selected.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {{ selected.error }}
                </div>

                <div v-if="selected.actions?.length" class="text-sm">
                    <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Automated actions
                    </h4>
                    <ul class="list-inside list-disc space-y-0.5 text-gray-700">
                        <li v-for="(action, i) in selected.actions" :key="i">{{ action }}</li>
                    </ul>
                </div>

                <template v-if="selected.status !== 'processed'">
                    <div>
                        <InputLabel value="Matter" />
                        <div class="mt-1 flex gap-2">
                            <SelectInput
                                v-model="assignForm.matter_id"
                                :options="matterOptions.map((m) => ({ value: m.id, label: `${m.reference} — ${m.title}` }))"
                                placeholder="Select the matter this belongs to…"
                                class="flex-1"
                            />
                            <SecondaryButton :disabled="!assignForm.matter_id || assignForm.processing" @click="assign">
                                Assign
                            </SecondaryButton>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                        <SecondaryButton @click="dismiss(selected)">Dismiss</SecondaryButton>
                        <PrimaryButton :disabled="!selected.matter" @click="processMessage(selected)">
                            Process Message
                        </PrimaryButton>
                    </div>
                </template>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
