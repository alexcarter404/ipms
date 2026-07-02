<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientForm from './Partials/ClientForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    countries: Array,
});

const form = useForm({
    code: '',
    name: '',
    type: 'company',
    email: '',
    phone: '',
    address: '',
    country_code: '',
    vat_number: '',
    notes: '',
});

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

        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
            <ClientForm :form="form" :countries="countries" submit-label="Create Client" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
