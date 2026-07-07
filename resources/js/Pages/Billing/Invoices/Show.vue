<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DateInput from '@/Components/DateInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    invoice: Object,
    paymentMethods: Array,
});

// Consolidated invoices (no single matter) render their lines grouped
// by the matter each line bills.
const lineGroups = computed(() => {
    const groups = [];
    let current = null;
    for (const line of props.invoice.lines) {
        const key = line.matter_id ?? 'general';
        if (!current || current.key !== key) {
            current = { key, matter: line.matter, lines: [] };
            groups.push(current);
        }
        current.lines.push(line);
    }
    return groups;
});

const isConsolidated = computed(() => props.invoice.matter_id === null);

const money = (amount) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.invoice.currency_code,
    }).format(amount ?? 0);

const shortDate = (value) =>
    value
        ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';

const confirm = useConfirm();
const confirmDelete = useDeleteConfirm();

const issue = () =>
    confirm.require({
        message: 'Issue this invoice? It gets the next invoice number and becomes final.',
        header: 'Issue Invoice',
        acceptLabel: 'Issue',
        rejectLabel: 'Cancel',
        accept: () => router.post(route('invoices.issue', props.invoice.id), {}, { preserveScroll: true }),
    });

const voidInvoice = () =>
    confirm.require({
        message: 'Void this invoice? Its items become billable again.',
        header: 'Void Invoice',
        acceptLabel: 'Void',
        rejectLabel: 'Cancel',
        acceptProps: { severity: 'danger' },
        accept: () => router.post(route('invoices.void', props.invoice.id), {}, { preserveScroll: true }),
    });

const destroy = () =>
    confirmDelete('Delete this draft invoice? Its items become billable again.', () =>
        router.delete(route('invoices.destroy', props.invoice.id))
    );

// --- record payment ---
const showPayment = ref(false);

const paymentForm = useForm({
    date: new Date().toISOString().slice(0, 10),
    amount: '',
    method: 'bank_transfer',
    reference: '',
});

const recordPayment = () =>
    paymentForm
        .transform((d) => ({ ...d, reference: d.reference || null }))
        .post(route('invoices.payments.store', props.invoice.id), {
            preserveScroll: true,
            onSuccess: () => {
                showPayment.value = false;
                paymentForm.reset();
            },
        });
</script>

<template>
    <Head :title="invoice.display_number" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ invoice.display_number }}
                    </h2>
                    <StatusBadge :status="invoice.status" />
                </div>
                <div class="flex gap-2">
                    <a
                        :href="route('invoices.pdf', invoice.id)"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        PDF
                    </a>
                    <a
                        :href="route('invoices.ledes', invoice.id)"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        LEDES
                    </a>
                    <PrimaryButton v-if="invoice.status === 'draft'" @click="issue">Issue Invoice</PrimaryButton>
                    <SecondaryButton
                        v-if="['issued', 'paid'].includes(invoice.status) && invoice.balance > 0"
                        @click="showPayment = true"
                    >
                        Record Payment
                    </SecondaryButton>
                    <SecondaryButton v-if="invoice.status === 'issued'" @click="voidInvoice">Void</SecondaryButton>
                    <DangerButton v-if="invoice.status === 'draft'" @click="destroy">Delete Draft</DangerButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Bill-to & meta -->
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-3 font-semibold text-gray-800">Bill To</h3>
                    <p class="text-sm font-medium text-gray-800">{{ invoice.entity?.name }}</p>
                    <p class="text-sm text-gray-600">{{ invoice.client?.name }}</p>
                    <p v-if="invoice.entity?.billing_address ?? invoice.entity?.address" class="mt-2 whitespace-pre-line text-sm text-gray-600">
                        {{ invoice.entity?.billing_address ?? invoice.entity?.address }}
                    </p>
                    <p v-if="invoice.entity?.vat_number" class="mt-2 text-sm text-gray-600">
                        VAT: {{ invoice.entity.vat_number }}
                    </p>
                    <p v-if="invoice.entity?.billing_reference" class="text-sm text-gray-600">
                        Your reference: {{ invoice.entity.billing_reference }}
                    </p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-3 font-semibold text-gray-800">Details</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Matter</dt>
                            <dd>
                                <Link
                                    v-if="invoice.matter"
                                    :href="route('matters.show', invoice.matter.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ invoice.matter.reference }}
                                </Link>
                                <span v-else class="text-gray-700">
                                    Consolidated — {{ lineGroups.filter((g) => g.matter).length }} matter(s)
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Currency</dt>
                            <dd class="font-medium text-gray-800">{{ invoice.currency_code }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Issued</dt>
                            <dd class="text-gray-700">{{ shortDate(invoice.issued_at) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Due</dt>
                            <dd class="text-gray-700">{{ shortDate(invoice.due_at) }}</dd>
                        </div>
                        <div v-if="invoice.tax_name" class="flex justify-between">
                            <dt class="text-gray-500">Tax treatment</dt>
                            <dd class="text-gray-700">{{ invoice.tax_name }} ({{ Number(invoice.tax_pct) }}%)</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Lines -->
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Unit</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template v-for="group in lineGroups" :key="group.key">
                            <tr v-if="isConsolidated && group.matter" class="bg-gray-50">
                                <td colspan="4" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <Link :href="route('matters.show', group.matter.id)" class="text-indigo-600 hover:underline">
                                        {{ group.matter.reference }}
                                    </Link>
                                    <span class="ml-2 font-normal normal-case text-gray-500">{{ group.matter.title }}</span>
                                </td>
                            </tr>
                            <tr v-for="line in group.lines" :key="line.id">
                                <td class="px-4 py-3 text-gray-700">{{ line.description }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-gray-600">{{ Number(line.quantity) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-gray-600">{{ money(line.unit_amount) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-gray-800">{{ money(line.line_total) }}</td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="text-sm">
                        <tr class="border-t border-gray-200">
                            <td colspan="3" class="px-4 py-2 text-right text-gray-500">Subtotal</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-gray-800">{{ money(invoice.subtotal) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-right text-gray-500">
                                {{ invoice.tax_name ?? 'Tax' }} ({{ Number(invoice.tax_pct) }}%)
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-gray-800">{{ money(invoice.tax_amount) }}</td>
                        </tr>
                        <tr class="border-t border-gray-200">
                            <td colspan="3" class="px-4 py-2.5 text-right font-semibold text-gray-700">Total</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-base font-semibold text-gray-900">{{ money(invoice.total) }}</td>
                        </tr>
                        <tr v-if="invoice.amount_paid > 0">
                            <td colspan="3" class="px-4 py-2 text-right text-gray-500">Paid</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-green-700">−{{ money(invoice.amount_paid) }}</td>
                        </tr>
                        <tr v-if="invoice.amount_paid > 0">
                            <td colspan="3" class="px-4 py-2 text-right font-semibold text-gray-700">Balance due</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-900">{{ money(invoice.balance) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Payments -->
            <div v-if="invoice.payments.length" class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-3 font-semibold text-gray-800">Payments</h3>
                <ul class="divide-y divide-gray-100 text-sm">
                    <li v-for="payment in invoice.payments" :key="payment.id" class="flex items-center justify-between py-2">
                        <span class="text-gray-600">
                            {{ shortDate(payment.date) }} — {{ payment.method.replace('_', ' ') }}
                            <span v-if="payment.reference" class="text-gray-500">({{ payment.reference }})</span>
                        </span>
                        <span class="font-medium text-gray-800">{{ money(payment.amount) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Record payment modal -->
        <Modal :show="showPayment" @close="showPayment = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Record Payment</h3>
                <p class="text-sm text-gray-600">Balance due: <strong>{{ money(invoice.balance) }}</strong></p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Date" />
                        <DateInput v-model="paymentForm.date" class="mt-1" />
                        <InputError :message="paymentForm.errors.date" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="`Amount (${invoice.currency_code})`" />
                        <TextInput v-model="paymentForm.amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="paymentForm.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Method" />
                        <SelectInput v-model="paymentForm.method" :options="paymentMethods" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Reference" />
                        <TextInput v-model="paymentForm.reference" class="mt-1 w-full" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showPayment = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="paymentForm.processing" @click="recordPayment">Record Payment</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
