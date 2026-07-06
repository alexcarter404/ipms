<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
});

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
        </div>

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
