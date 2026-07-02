<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RuleForm from './Partials/RuleForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    types: Array,
    countries: Array,
});

const form = useForm({
    name: '',
    matter_type: 'patent',
    country_code: '',
    base_date: 'application',
    schedule_mode: 'regular',
    start_cycle: 2,
    end_cycle: 20,
    interval_years: 1,
    offsets_months: [],
    grace_months: 6,
    default_official_fee: '',
    default_service_fee: '',
    currency: '',
    is_active: true,
    notes: '',
});

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            country_code: d.country_code || null,
            default_official_fee: d.default_official_fee || null,
            default_service_fee: d.default_service_fee || null,
            currency: d.currency ? d.currency.toUpperCase() : null,
            notes: d.notes || null,
            offsets_months: d.offsets_months.filter((m) => m !== '' && m !== null),
        }))
        .post(route('renewal-rules.store'));
</script>

<template>
    <Head title="New Renewal Rule" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                New Renewal Rule
            </h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <RuleForm
                :form="form"
                :types="types"
                :countries="countries"
                submit-label="Create Rule"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
