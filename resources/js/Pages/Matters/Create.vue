<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MatterForm from './Partials/MatterForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    options: Object,
    preselectedClientId: Number,
});

const form = useForm({
    reference: '',
    matter_type: 'patent',
    title: '',
    client_id: props.preselectedClientId ?? '',
    contact_id: null,
    family_id: '',
    parent_id: '',
    responsible_user_id: '',
    country_code: '',
    filing_route: '',
    status: 'draft',
    application_no: '',
    application_date: '',
    publication_no: '',
    publication_date: '',
    registration_no: '',
    registration_date: '',
    priority_no: '',
    priority_date: '',
    expiry_date: '',
    description: '',
    notes: '',
});

const submit = () =>
    form
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])
            )
        )
        .post(route('matters.store'));
</script>

<template>
    <Head title="New Matter" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                New Matter
            </h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <MatterForm :form="form" :options="options" submit-label="Create Matter" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
