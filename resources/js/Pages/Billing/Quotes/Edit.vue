<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import QuoteForm from './Partials/QuoteForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    quote: Object,
    quoteTaxRateId: { type: Number, default: null },
    clients: Array,
    matters: Array,
    currencies: Array,
    taxRates: Array,
});

const form = useForm({
    client_id: props.quote.client_id,
    client_entity_id: props.quote.client_entity_id ?? '',
    matter_id: props.quote.matter_id ?? '',
    currency_code: props.quote.currency_code,
    tax_rate_id: props.quoteTaxRateId ?? '',
    valid_until: props.quote.valid_until?.slice(0, 10) ?? '',
    notes: props.quote.notes ?? '',
    lines: props.quote.lines.map((l) => ({
        description: l.description,
        quantity: Number(l.quantity),
        unit_amount: Number(l.unit_amount),
    })),
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
        .patch(route('quotes.update', props.quote.id));

const setStatus = (status) =>
    router.patch(route('quotes.status', props.quote.id), { status }, { preserveScroll: true });

const confirmDelete = useDeleteConfirm();

const destroy = () =>
    confirmDelete(`Delete quote ${props.quote.quote_no}?`, () =>
        router.delete(route('quotes.destroy', props.quote.id))
    );
</script>

<template>
    <Head :title="quote.quote_no" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Quote {{ quote.quote_no }}
                    </h2>
                    <StatusBadge :status="quote.status" />
                </div>
                <div class="flex gap-2">
                    <SecondaryButton v-if="quote.status === 'draft'" @click="setStatus('sent')">
                        Mark Sent
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="['draft', 'sent'].includes(quote.status)"
                        @click="setStatus('accepted')"
                    >
                        Mark Accepted
                    </SecondaryButton>
                    <SecondaryButton
                        v-if="['draft', 'sent'].includes(quote.status)"
                        @click="setStatus('declined')"
                    >
                        Mark Declined
                    </SecondaryButton>
                    <DangerButton @click="destroy">Delete</DangerButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <p v-if="quote.status !== 'draft'" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                This quote is {{ quote.status }} — it can no longer be edited.
            </p>
            <QuoteForm
                :form="form"
                :clients="clients"
                :matters="matters"
                :currencies="currencies"
                :tax-rates="taxRates"
                submit-label="Save Changes"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
