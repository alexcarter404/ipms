<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import BillingPanel from './Partials/BillingPanel.vue';
import ClassesPanel from './Partials/ClassesPanel.vue';
import CommsPanel from './Partials/CommsPanel.vue';
import ContactsPanel from './Partials/ContactsPanel.vue';
import PartiesPanel from './Partials/PartiesPanel.vue';
import RenewalsPanel from './Partials/RenewalsPanel.vue';
import TasksPanel from './Partials/TasksPanel.vue';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import Tabs from 'primevue/tabs';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    matter: Object,
    countryName: String,
    renewalRule: { type: Object, default: null },
    billingEntity: { type: Object, default: null },
    parties: Array,
    partyRoles: Array,
    clientContacts: Array,
    contactRoles: Array,
    contactTypes: Array,
    workflows: Array,
    triggerEvents: Array,
    templates: Array,
    users: Array,
    priorities: Array,
    baseDates: Object,
    billingAgreement: { type: Object, default: null },
    billing: Object,
    billingOptions: Object,
});

const tabs = computed(() => {
    const openTasks = props.matter.tasks.filter((t) =>
        ['pending', 'in_progress'].includes(t.status)
    ).length;
    const openRenewals = props.matter.renewals.filter((r) =>
        ['upcoming', 'reminder_sent', 'instructed'].includes(r.status)
    ).length;

    const list = [
        { key: 'overview', label: 'Overview' },
        { key: 'contacts', label: `Contacts (${props.matter.contacts.length})` },
        { key: 'parties', label: `Parties (${props.matter.parties.length})` },
    ];
    if (['trademark', 'design'].includes(props.matter.matter_type)) {
        list.push({ key: 'classes', label: `Classes (${props.matter.classes.length})` });
    }
    list.push(
        { key: 'tasks', label: `Tasks (${openTasks})` },
        { key: 'renewals', label: `Renewals (${openRenewals})` },
        { key: 'billing', label: 'Billing' },
        { key: 'comms', label: `Comms (${props.matter.communications.length})` }
    );
    return list;
});

const activeTab = ref('overview');

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';

const confirmDelete = useDeleteConfirm();

const destroy = () =>
    confirmDelete(`Delete matter ${props.matter.reference}? It can be restored from the database.`, () =>
        router.delete(route('matters.destroy', props.matter.id))
    );

const details = computed(() => [
    { label: 'Client', value: props.matter.client?.name, link: props.matter.client ? route('clients.show', props.matter.client.id) : null },
    {
        label: 'Main contact',
        value: props.matter.contacts.find((c) => c.pivot.role === 'main')?.name,
    },
    {
        label: 'Docketing',
        value: props.matter.contacts.find((c) => c.pivot.role === 'docketing')?.email,
    },
    {
        label: 'Billing entity',
        value: props.billingEntity
            ? props.billingEntity.name + (props.billingEntity.is_fallback ? ' (client default)' : '')
            : null,
    },
    { label: 'Jurisdiction', value: props.countryName },
    { label: 'Filing route', value: props.matter.filing_route?.toUpperCase() },
    { label: 'Responsible attorney', value: props.matter.responsible_user?.name },
    { label: 'Family', value: props.matter.family ? `${props.matter.family.reference} — ${props.matter.family.name}` : null },
    { label: 'Parent matter', value: props.matter.parent?.reference, link: props.matter.parent ? route('matters.show', props.matter.parent.id) : null },
]);

const officialDates = computed(() => [
    { label: 'Priority', no: props.matter.priority_no, date: props.matter.priority_date },
    { label: 'Application', no: props.matter.application_no, date: props.matter.application_date },
    { label: 'Publication', no: props.matter.publication_no, date: props.matter.publication_date },
    { label: 'Registration / Grant', no: props.matter.registration_no, date: props.matter.registration_date },
    { label: 'Expiry', no: null, date: props.matter.expiry_date },
]);
</script>

<template>
    <Head :title="matter.reference" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">
                            {{ matter.reference }}
                        </h2>
                        <StatusBadge :status="matter.status" />
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium uppercase text-gray-600">
                            {{ matter.matter_type }} · {{ matter.country_code }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">{{ matter.title }}</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('matters.edit', matter.id)"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Edit
                    </Link>
                    <DangerButton @click="destroy">Delete</DangerButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Tabs -->
            <Tabs v-model:value="activeTab" :pt="{ root: { class: '!bg-transparent' } }">
                <TabList :pt="{ tabList: { class: '!bg-transparent' } }">
                    <Tab v-for="tab in tabs" :key="tab.key" :value="tab.key">
                        {{ tab.label }}
                    </Tab>
                </TabList>
            </Tabs>

            <!-- Overview -->
            <div v-if="activeTab === 'overview'" class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 font-semibold text-gray-800">Details</h3>
                    <dl class="space-y-2.5 text-sm">
                        <div v-for="row in details" :key="row.label" class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ row.label }}</dt>
                            <dd class="text-right font-medium text-gray-800">
                                <Link v-if="row.link && row.value" :href="row.link" class="text-indigo-600 hover:underline">
                                    {{ row.value }}
                                </Link>
                                <template v-else>{{ row.value ?? '—' }}</template>
                            </dd>
                        </div>
                    </dl>

                    <template v-if="matter.children?.length">
                        <h4 class="mb-2 mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Child matters
                        </h4>
                        <ul class="space-y-1 text-sm">
                            <li v-for="child in matter.children" :key="child.id">
                                <Link :href="route('matters.show', child.id)" class="text-indigo-600 hover:underline">
                                    {{ child.reference }}
                                </Link>
                                <span class="text-gray-500"> — {{ child.title }} ({{ child.country_code }})</span>
                            </li>
                        </ul>
                    </template>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="mb-4 font-semibold text-gray-800">Official Numbers &amp; Dates</h3>
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="py-1.5">Event</th>
                                    <th class="py-1.5">Number</th>
                                    <th class="py-1.5">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in officialDates" :key="row.label" class="border-t border-gray-100">
                                    <td class="py-2 text-gray-500">{{ row.label }}</td>
                                    <td class="py-2 font-medium text-gray-800">{{ row.no ?? '—' }}</td>
                                    <td class="py-2 text-gray-700">{{ formatDate(row.date) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="matter.description || matter.notes" class="rounded-lg bg-white p-6 shadow-sm">
                        <template v-if="matter.description">
                            <h3 class="mb-2 font-semibold text-gray-800">Description</h3>
                            <p class="whitespace-pre-wrap text-sm text-gray-600">{{ matter.description }}</p>
                        </template>
                        <template v-if="matter.notes">
                            <h3 class="mb-2 mt-4 font-semibold text-gray-800">Internal Notes</h3>
                            <p class="whitespace-pre-wrap text-sm text-gray-600">{{ matter.notes }}</p>
                        </template>
                    </div>
                </div>
            </div>

            <ContactsPanel
                v-else-if="activeTab === 'contacts'"
                :matter="matter"
                :client-contacts="clientContacts"
                :contact-roles="contactRoles"
                :contact-types="contactTypes"
            />
            <PartiesPanel
                v-else-if="activeTab === 'parties'"
                :matter="matter"
                :parties="parties"
                :party-roles="partyRoles"
            />
            <ClassesPanel v-else-if="activeTab === 'classes'" :matter="matter" />
            <TasksPanel
                v-else-if="activeTab === 'tasks'"
                :matter="matter"
                :users="users"
                :priorities="priorities"
                :workflows="workflows"
                :base-dates="baseDates"
            />
            <RenewalsPanel
                v-else-if="activeTab === 'renewals'"
                :matter="matter"
                :renewal-rule="renewalRule"
            />
            <BillingPanel
                v-else-if="activeTab === 'billing'"
                :matter="matter"
                :agreement="billingAgreement"
                :billing="billing"
                :options="billingOptions"
                :users="users"
            />
            <CommsPanel v-else-if="activeTab === 'comms'" :matter="matter" :templates="templates" />
        </div>
    </AuthenticatedLayout>
</template>
