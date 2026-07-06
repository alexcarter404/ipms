<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuoteForm from './Partials/QuoteForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    clients: Array,
    matters: Array,
    currencies: Array,
    taxRates: Array,
});

const form = useForm({
    client_id: '',
    client_entity_id: '',
    matter_id: '',
    currency_code: 'GBP',
    tax_rate_id: '',
    valid_until: '',
    notes: '',
    lines: [{ description: '', quantity: 1, unit_amount: '' }],
});

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            client_entity_id: d.client_entity_id || null,
            matter_id: d.matter_id || null,
            tax_rate_id: d.tax_rate_id || null,
            valid_until: d.valid_until || null,
        }))
        .post(route('quotes.store'));
</script>

<template>
    <Head title="New Quote" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Quote</h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <QuoteForm
                :form="form"
                :clients="clients"
                :matters="matters"
                :currencies="currencies"
                :tax-rates="taxRates"
                submit-label="Create Quote"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
