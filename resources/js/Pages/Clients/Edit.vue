<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClientForm from './Partials/ClientForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    client: Object,
    countries: Array,
});

const form = useForm({
    code: props.client.code,
    name: props.client.name,
    type: props.client.type,
    email: props.client.email ?? '',
    phone: props.client.phone ?? '',
    address: props.client.address ?? '',
    country_code: props.client.country_code ?? '',
    vat_number: props.client.vat_number ?? '',
    notes: props.client.notes ?? '',
});

const submit = () =>
    form
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])
            )
        )
        .patch(route('clients.update', props.client.id));
</script>

<template>
    <Head :title="`Edit ${client.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edit {{ client.name }}
                </h2>
                <Link :href="route('clients.show', client.id)" class="text-sm text-indigo-600 hover:underline">
                    Back to client
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
            <ClientForm :form="form" :countries="countries" submit-label="Save Changes" @submit="submit" />
        </div>
    </AuthenticatedLayout>
</template>
