<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MatterForm from './Partials/MatterForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    matter: Object,
    options: Object,
});

const dateOnly = (value) => (value ? value.substring(0, 10) : '');

const form = useForm({
    reference: props.matter.reference,
    matter_type: props.matter.matter_type,
    title: props.matter.title,
    client_id: props.matter.client_id,
    client_entity_id: props.matter.client_entity_id ?? '',
    family_id: props.matter.family_id ?? '',
    parent_id: props.matter.parent_id ?? '',
    responsible_user_id: props.matter.responsible_user_id ?? '',
    country_code: props.matter.country_code,
    filing_route: props.matter.filing_route ?? '',
    status: props.matter.status,
    application_no: props.matter.application_no ?? '',
    application_date: dateOnly(props.matter.application_date),
    publication_no: props.matter.publication_no ?? '',
    publication_date: dateOnly(props.matter.publication_date),
    registration_no: props.matter.registration_no ?? '',
    registration_date: dateOnly(props.matter.registration_date),
    priority_no: props.matter.priority_no ?? '',
    priority_date: dateOnly(props.matter.priority_date),
    expiry_date: dateOnly(props.matter.expiry_date),
    description: props.matter.description ?? '',
    notes: props.matter.notes ?? '',
});

const submit = () =>
    form
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])
            )
        )
        .patch(route('matters.update', props.matter.id));
</script>

<template>
    <Head :title="`Edit ${matter.reference}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit {{ matter.reference }}
                </h2>
                <Link
                    :href="route('matters.show', matter.id)"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    Back to matter
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <MatterForm :form="form" :options="options" submit-label="Save Changes" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
