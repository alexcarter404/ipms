<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientForm from './Partials/ClientForm.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Tag from 'primevue/tag';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    countries: Array,
});

const form = useForm({
    code: '',
    name: '',
    type: 'company',
    email: '',
    phone: '',
    country_code: '',
    notes: '',
});

// Conflict search: does this name already exist anywhere in the practice?
const conflicts = ref(null);
const checking = ref(false);

const checkConflicts = async () => {
    checking.value = true;
    const response = await fetch(
        route('conflict-check') + '?name=' + encodeURIComponent(form.name),
        { headers: { Accept: 'application/json' } }
    );
    conflicts.value = (await response.json()).matches;
    checking.value = false;
};

const submit = () =>
    form
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])
            )
        )
        .post(route('clients.store'));
</script>

<template>
    <Head title="New Client" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Client</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Conflict check -->
            <div class="rounded-lg bg-white p-5 shadow-sm" data-testid="conflict-check">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">Conflict check</h3>
                        <p class="text-sm text-gray-500">
                            Search the practice for this name before taking the client on —
                            existing clients, entities, contacts and opposing parties.
                        </p>
                    </div>
                    <SecondaryButton :disabled="form.name.length < 3 || checking" @click="checkConflicts">
                        Run Conflict Check
                    </SecondaryButton>
                </div>

                <div v-if="conflicts !== null" class="mt-4">
                    <p v-if="!conflicts.length" class="text-sm font-medium text-green-700">
                        No matches — clear to proceed.
                    </p>
                    <ul v-else class="space-y-1.5">
                        <li v-for="(match, i) in conflicts" :key="i" class="flex items-center gap-2 text-sm">
                            <Tag
                                :value="match.type"
                                :severity="match.type === 'Party' ? 'warn' : 'info'"
                                class="!text-xs"
                            />
                            <span class="font-medium text-gray-800">{{ match.name }}</span>
                            <span class="text-gray-500">{{ match.detail }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <ClientForm :form="form" :countries="countries" submit-label="Create Client" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
