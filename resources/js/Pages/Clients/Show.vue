<script setup>
import AuditTrail from '@/Components/AuditTrail.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EntitiesPanel from './Partials/EntitiesPanel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    client: Object,
    countries: Array,
    contactTypes: Array,
    matters: Object,
    billingCurrencies: Array,
    taxRates: Array,
    agreementTypes: Array,
    audits: { type: Array, default: () => [] },
});

const typeLabel = (value) =>
    props.contactTypes.find((t) => t.value === value)?.label ?? value;

const showContactForm = ref(false);

const contactForm = useForm({
    name: '',
    type: 'person',
    email: '',
    phone: '',
    position: '',
    is_primary: false,
});

const submitContact = () =>
    contactForm
        .transform((d) => ({
            ...d,
            email: d.email || null,
            phone: d.phone || null,
            position: d.position || null,
        }))
        .post(route('clients.contacts.store', props.client.id), {
            preserveScroll: true,
            onSuccess: () => {
                contactForm.reset();
                showContactForm.value = false;
            },
        });

const confirmDelete = useDeleteConfirm();

const onPage = (event) =>
    router.get(
        route('clients.show', props.client.id),
        { page: event.page + 1 },
        { preserveState: true, preserveScroll: true, replace: true }
    );

const removeContact = (contact) =>
    confirmDelete(`Remove contact ${contact.name}?`, () =>
        router.delete(route('contacts.destroy', contact.id), { preserveScroll: true }), 'Remove');
</script>

<template>
    <Head :title="client.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ client.name }}
                        <span class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                            {{ client.code }}
                        </span>
                    </h2>
                    <p class="mt-1 text-sm capitalize text-gray-600">
                        {{ client.type }}
                        <template v-if="client.country_code"> · {{ client.country_code }}</template>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('matters.create', { client_id: client.id })"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        New Matter
                    </Link>
                    <Link
                        :href="route('clients.edit', client.id)"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Edit
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Client details -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 font-semibold text-gray-800">Details</h3>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Email</dt>
                            <dd class="font-medium text-gray-800">{{ client.email ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Phone</dt>
                            <dd class="font-medium text-gray-800">{{ client.phone ?? '—' }}</dd>
                        </div>
                        <div v-if="client.notes">
                            <dt class="text-gray-500">Notes</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-gray-700">{{ client.notes }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Contacts -->
                <div class="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Contacts</h3>
                        <button
                            type="button"
                            class="text-sm text-indigo-600 hover:underline"
                            @click="showContactForm = !showContactForm"
                        >
                            {{ showContactForm ? 'Close' : 'Add contact' }}
                        </button>
                    </div>

                    <form
                        v-if="showContactForm"
                        class="mb-4 grid gap-3 rounded-md bg-gray-50 p-4 sm:grid-cols-2"
                        @submit.prevent="submitContact"
                    >
                        <div>
                            <InputLabel value="Name *" />
                            <TextInput v-model="contactForm.name" class="mt-1 w-full" />
                            <InputError :message="contactForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Type *" />
                            <SelectInput v-model="contactForm.type" :options="contactTypes" class="mt-1" />
                            <InputError :message="contactForm.errors.type" class="mt-1" />
                        </div>
                        <div v-if="contactForm.type === 'person'">
                            <InputLabel value="Position" />
                            <TextInput v-model="contactForm.position" class="mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel value="Email" />
                            <TextInput v-model="contactForm.email" type="email" class="mt-1 w-full" />
                            <InputError :message="contactForm.errors.email" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Phone" />
                            <TextInput v-model="contactForm.phone" class="mt-1 w-full" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="contactForm.is_primary" type="checkbox" class="rounded text-indigo-600" />
                            Primary contact
                        </label>
                        <div class="sm:col-span-2">
                            <PrimaryButton :disabled="contactForm.processing">Save Contact</PrimaryButton>
                        </div>
                    </form>

                    <ul class="divide-y">
                        <li
                            v-for="contact in client.contacts"
                            :key="contact.id"
                            class="flex items-center justify-between py-2.5 text-sm"
                        >
                            <div>
                                <span class="font-medium text-gray-800">{{ contact.name }}</span>
                                <span
                                    v-if="contact.is_primary"
                                    class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700"
                                    >Primary</span
                                >
                                <span
                                    v-if="contact.type !== 'person'"
                                    class="ml-2 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800"
                                    >{{ typeLabel(contact.type) }}</span
                                >
                                <div class="text-xs text-gray-500">
                                    {{ contact.position ?? '' }}
                                    <template v-if="contact.email"> · {{ contact.email }}</template>
                                    <template v-if="contact.phone"> · {{ contact.phone }}</template>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="text-xs text-red-600 hover:underline"
                                @click="removeContact(contact)"
                            >
                                Remove
                            </button>
                        </li>
                        <li v-if="!client.contacts.length" class="py-4 text-center text-sm text-gray-500">
                            No contacts yet.
                        </li>
                    </ul>
                </div>
            </div>

            <EntitiesPanel
                :client="client"
                :countries="countries"
                :billing-currencies="billingCurrencies"
                :tax-rates="taxRates"
                :agreement-types="agreementTypes"
            />

            <!-- Matters -->
            <DataTable
                :value="matters.data"
                lazy
                paginator
                :rows="matters.per_page"
                :total-records="matters.total"
                :first="(matters.current_page - 1) * matters.per_page"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
                @page="onPage"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">No matters for this client yet.</p>
                </template>

                <Column header="Reference">
                    <template #body="{ data }">
                        <Link
                            :href="route('matters.show', data.id)"
                            class="whitespace-nowrap font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.reference }}
                        </Link>
                    </template>
                </Column>
                <Column header="Title">
                    <template #body="{ data }">
                        <span class="block max-w-xs truncate text-gray-700">{{ data.title }}</span>
                    </template>
                </Column>
                <Column header="Type">
                    <template #body="{ data }">
                        <span class="capitalize text-gray-600">{{ data.matter_type }}</span>
                    </template>
                </Column>
                <Column field="country_code" header="Ctry" />
                <Column header="Status">
                    <template #body="{ data }">
                        <StatusBadge :status="data.status" />
                    </template>
                </Column>
                <Column header="Attorney">
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.responsible_user?.name ?? '—' }}</span>
                    </template>
                </Column>
            </DataTable>

            <!-- Audit history -->
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-1 font-semibold text-gray-800">Audit history</h3>
                <p class="mb-5 text-sm text-gray-500">
                    Every change to this client, its entities and contacts — who, when, and what
                    moved. Each entry captures a state the record can be restored to.
                </p>
                <AuditTrail :audits="audits" empty-text="No audited activity on this client yet." />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
