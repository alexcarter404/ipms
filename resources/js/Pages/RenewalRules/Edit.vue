<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RuleForm from './Partials/RuleForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    rule: Object,
    types: Array,
    countries: Array,
});

const form = useForm({
    name: props.rule.name,
    matter_type: props.rule.matter_type,
    country_code: props.rule.country_code ?? '',
    base_date: props.rule.base_date,
    schedule_mode: props.rule.offsets_months === null ? 'regular' : 'fixed',
    start_cycle: props.rule.start_cycle ?? '',
    end_cycle: props.rule.end_cycle ?? '',
    interval_years: props.rule.interval_years ?? '',
    offsets_months: props.rule.offsets_months ?? [],
    grace_months: props.rule.grace_months,
    default_official_fee: props.rule.default_official_fee ?? '',
    default_service_fee: props.rule.default_service_fee ?? '',
    currency: props.rule.currency ?? '',
    is_active: props.rule.is_active,
    notes: props.rule.notes ?? '',
});

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            country_code: d.country_code || null,
            start_cycle: d.start_cycle || null,
            end_cycle: d.end_cycle || null,
            interval_years: d.interval_years || null,
            default_official_fee: d.default_official_fee || null,
            default_service_fee: d.default_service_fee || null,
            currency: d.currency ? d.currency.toUpperCase() : null,
            notes: d.notes || null,
            offsets_months: d.offsets_months.filter((m) => m !== '' && m !== null),
        }))
        .patch(route('renewal-rules.update', props.rule.id));
</script>

<template>
    <Head :title="`Edit ${rule.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Renewal Rule — {{ rule.name }}
            </h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <RuleForm
                :form="form"
                :types="types"
                :countries="countries"
                submit-label="Save Changes"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
