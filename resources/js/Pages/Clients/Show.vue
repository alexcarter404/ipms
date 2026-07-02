<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EntitiesPanel from './Partials/EntitiesPanel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    client: Object,
    countries: Array,
    contactTypes: Array,
    matters: Object,
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

const removeContact = (contact) => {
    if (!confirm(`Remove contact ${contact.name}?`)) return;
    router.delete(route('contacts.destroy', contact.id), { preserveScroll: true });
};
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

            <EntitiesPanel :client="client" :countries="countries" />

            <!-- Matters -->
            <div class="space-y-4">
                <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">Title</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Ctry</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Attorney</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="matter in matters.data" :key="matter.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <Link
                                        :href="route('matters.show', matter.id)"
                                        class="font-medium text-indigo-600 hover:underline"
                                    >
                                        {{ matter.reference }}
                                    </Link>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 text-gray-700">{{ matter.title }}</td>
                                <td class="px-4 py-3 capitalize text-gray-600">{{ matter.matter_type }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ matter.country_code }}</td>
                                <td class="px-4 py-3"><StatusBadge :status="matter.status" /></td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ matter.responsible_user?.name ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="!matters.data.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No matters for this client yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :links="matters.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
