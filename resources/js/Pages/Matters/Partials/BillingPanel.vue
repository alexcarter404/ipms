<script setup>
import DateInput from '@/Components/DateInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    matter: Object,
    agreement: { type: Object, default: null },
    agreementSource: { type: String, default: null }, // 'matter' | 'entity' | null
    budget: { type: Object, default: null },
    billing: Object,
    options: Object,
    users: Array,
});

const money = (amount, currency) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency ?? props.billing.currency,
    }).format(amount ?? 0);

const shortDate = (value) =>
    value
        ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';

const agreementTypeLabel = computed(
    () =>
        props.options.agreementTypes.find((t) => t.value === props.agreement?.type)?.label ??
        'Hourly (default)'
);

const userOptions = computed(() => props.users.map((u) => ({ value: u.id, label: u.name })));

// --- budgets ---
const showBudget = ref(false);
const editingBudget = ref(null); // null = add, object = amend

const budgetForm = useForm({
    amount: '',
    currency_code: '',
    description: '',
});

const openBudget = (row = null) => {
    editingBudget.value = row;
    budgetForm.defaults({
        amount: row ? row.amount : '',
        currency_code: row ? row.currency : props.billing.currency,
        description: row?.description ?? '',
    });
    budgetForm.reset();
    budgetForm.clearErrors();
    showBudget.value = true;
};

const saveBudget = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showBudget.value = false),
    };
    const transform = (d) => ({ ...d, description: d.description || null });

    editingBudget.value
        ? budgetForm.transform(transform).patch(route('budgets.update', editingBudget.value.id), options)
        : budgetForm.transform(transform).post(route('matters.budgets.store', props.matter.id), options);
};

const utilisationColor = (pct) =>
    pct === null ? 'bg-gray-300' : pct > 100 ? 'bg-red-500' : pct > 75 ? 'bg-amber-500' : 'bg-green-500';

// --- agreement editor ---
const showAgreement = ref(false);

const agreementForm = useForm({
    type: props.agreement?.type ?? 'hourly',
    currency_code: props.agreement?.currency_code ?? '',
    increment_minutes: props.agreement?.increment_minutes ?? 6,
    blended_rate: props.agreement?.blended_rate ?? '',
    cap_amount: props.agreement?.cap_amount ?? '',
    fixed_amount: props.agreement?.fixed_amount ?? '',
    default_markup_pct: props.agreement?.default_markup_pct ?? 0,
    requires_task_codes: props.agreement?.requires_task_codes ?? false,
    notes: props.agreement?.notes ?? '',
    stages: (props.agreement?.stages ?? []).map((s) => ({
        id: s.id,
        description: s.description,
        amount: s.amount,
    })),
});

const addStage = () => agreementForm.stages.push({ id: null, description: '', amount: '' });
const removeStage = (index) => agreementForm.stages.splice(index, 1);

const removeOverride = () =>
    router.delete(route('matters.agreement.destroy', props.matter.id), { preserveScroll: true });

const saveAgreement = () =>
    agreementForm
        .transform((d) => ({
            ...d,
            currency_code: d.currency_code || null,
            blended_rate: d.blended_rate || null,
            cap_amount: d.cap_amount || null,
            fixed_amount: d.fixed_amount || null,
            default_markup_pct: d.default_markup_pct || 0,
        }))
        .post(route('matters.agreement.save', props.matter.id), {
            preserveScroll: true,
            onSuccess: () => (showAgreement.value = false),
        });

// --- log time ---
const showTime = ref(false);

const timeForm = useForm({
    user_id: '',
    work_date: new Date().toISOString().slice(0, 10),
    minutes: '',
    activity_code_id: '',
    narrative: '',
    status: 'billable',
});

const logTime = () =>
    timeForm
        .transform((d) => ({ ...d, activity_code_id: d.activity_code_id || null }))
        .post(route('matters.time.store', props.matter.id), {
            preserveScroll: true,
            onSuccess: () => {
                showTime.value = false;
                timeForm.reset();
            },
        });

// --- disbursement ---
const showDisbursement = ref(false);

const disbursementForm = useForm({
    date: new Date().toISOString().slice(0, 10),
    description: '',
    supplier: '',
    cost_amount: '',
    cost_currency: props.billing.currency,
    markup_pct: '',
});

const addDisbursement = () =>
    disbursementForm
        .transform((d) => ({ ...d, supplier: d.supplier || null, markup_pct: d.markup_pct === '' ? null : d.markup_pct }))
        .post(route('matters.disbursements.store', props.matter.id), {
            preserveScroll: true,
            onSuccess: () => {
                showDisbursement.value = false;
                disbursementForm.reset();
            },
        });

// --- charge ---
const showCharge = ref(false);

const chargeForm = useForm({
    type: 'fixed_fee',
    date: new Date().toISOString().slice(0, 10),
    description: '',
    amount: '',
});

const addCharge = () =>
    chargeForm.post(route('matters.charges.store', props.matter.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCharge.value = false;
            chargeForm.reset();
        },
    });

// --- draft invoice ---
const showInvoice = ref(false);

const invoiceForm = useForm({
    include_time: true,
    include_disbursements: true,
    include_charges: true,
    through: '',
});

const draftInvoice = () =>
    invoiceForm
        .transform((d) => ({ ...d, through: d.through || null }))
        .post(route('matters.invoices.store', props.matter.id), {
            onError: () => (showInvoice.value = false),
        });

// --- item actions ---
const confirmDelete = useDeleteConfirm();

const writeOff = (routeName, item) =>
    router.patch(route(routeName, item.id), { status: 'written_off' }, { preserveScroll: true });

const reinstate = (routeName, item) =>
    router.patch(route(routeName, item.id), { status: 'billable' }, { preserveScroll: true });

const removeItem = (routeName, item, label) =>
    confirmDelete(`Delete this ${label}?`, () =>
        router.delete(route(routeName, item.id), { preserveScroll: true })
    );

const raiseStage = (stage) =>
    router.post(route('agreement-stages.charge', stage.id), {}, { preserveScroll: true });
</script>

<template>
    <div class="space-y-6" data-testid="billing-panel">
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Fee agreement -->
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="flex items-center gap-2 font-semibold text-gray-800">
                        Fee Agreement
                        <span
                            v-if="agreementSource === 'entity'"
                            class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700"
                            title="Handed down from the billing entity's default agreement"
                        >
                            Inherited from entity
                        </span>
                    </h3>
                    <div class="flex gap-2">
                        <button
                            v-if="agreementSource === 'matter'"
                            class="text-xs text-gray-500 hover:underline"
                            @click="removeOverride"
                        >
                            Remove override
                        </button>
                        <SecondaryButton @click="showAgreement = true">
                            {{
                                agreementSource === 'matter'
                                    ? 'Edit Agreement'
                                    : agreementSource === 'entity'
                                      ? 'Override for Matter'
                                      : 'Set Agreement'
                            }}
                        </SecondaryButton>
                    </div>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Arrangement</dt>
                        <dd class="font-medium text-gray-800">{{ agreementTypeLabel }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Billing currency</dt>
                        <dd class="font-medium text-gray-800">
                            {{ billing.currency }}
                            <span v-if="!agreement?.currency_code" class="text-xs text-gray-500">(entity)</span>
                        </dd>
                    </div>
                    <div v-if="agreement?.type === 'blended'" class="flex justify-between">
                        <dt class="text-gray-500">Blended rate</dt>
                        <dd class="font-medium text-gray-800">{{ money(agreement.blended_rate) }}/h</dd>
                    </div>
                    <div v-if="agreement?.type === 'capped'" class="flex justify-between">
                        <dt class="text-gray-500">Fee cap</dt>
                        <dd class="font-medium text-gray-800">{{ money(agreement.cap_amount) }}</dd>
                    </div>
                    <div v-if="agreement?.type === 'fixed'" class="flex justify-between">
                        <dt class="text-gray-500">Fixed fee</dt>
                        <dd class="font-medium text-gray-800">{{ money(agreement.fixed_amount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Time increment</dt>
                        <dd class="text-gray-700">{{ agreement?.increment_minutes ?? 6 }} minutes</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Disbursement markup</dt>
                        <dd class="text-gray-700">{{ agreement?.default_markup_pct ?? 0 }}%</dd>
                    </div>
                    <div v-if="agreement?.requires_task_codes" class="flex justify-between">
                        <dt class="text-gray-500">Task-based billing</dt>
                        <dd class="text-gray-700">Task codes required</dd>
                    </div>
                </dl>

                <template v-if="agreement?.type === 'stage' && agreement.stages?.length">
                    <h4 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Stage payments
                    </h4>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="stage in agreement.stages" :key="stage.id" class="flex items-center justify-between py-2">
                            <span class="text-gray-700">{{ stage.description }}</span>
                            <span class="flex items-center gap-3">
                                <span class="font-medium text-gray-800">{{ money(stage.amount) }}</span>
                                <span v-if="stage.charge" class="text-xs text-green-700">✓ charged</span>
                                <button v-else class="text-xs text-indigo-600 hover:underline" @click="raiseStage(stage)">
                                    Raise charge
                                </button>
                            </span>
                        </li>
                    </ul>
                </template>
            </div>

            <!-- WIP summary -->
            <div class="rounded-lg bg-white p-6 shadow-sm" data-testid="wip-summary">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Unbilled Work in Progress</h3>
                    <PrimaryButton :disabled="billing.wip.total <= 0" @click="showInvoice = true">
                        Draft Invoice
                    </PrimaryButton>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Time</dt>
                        <dd class="font-medium text-gray-800">{{ money(billing.wip.time) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Disbursements</dt>
                        <dd class="font-medium text-gray-800">{{ money(billing.wip.disbursements) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Charges</dt>
                        <dd class="font-medium text-gray-800">{{ money(billing.wip.charges) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-2">
                        <dt class="font-semibold text-gray-700">Total WIP</dt>
                        <dd class="text-right font-semibold text-gray-900">
                            {{ money(billing.wip.total) }}
                            <span
                                v-if="billing.wip.currency !== billing.wip.base_currency"
                                class="block text-xs font-normal text-gray-500"
                            >
                                ≈ {{ money(billing.wip.base_total, billing.wip.base_currency) }} base
                            </span>
                        </dd>
                    </div>
                </dl>

                <template v-if="billing.invoices.length">
                    <h4 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Invoices
                    </h4>
                    <ul class="divide-y divide-gray-100 text-sm">
                        <li v-for="invoice in billing.invoices" :key="invoice.id" class="flex items-center justify-between py-2">
                            <Link :href="route('invoices.show', invoice.id)" class="text-indigo-600 hover:underline">
                                {{ invoice.invoice_no ?? `Draft #${invoice.id}` }}
                            </Link>
                            <span class="flex items-center gap-3">
                                <span class="text-gray-700">{{ money(invoice.total, invoice.currency_code) }}</span>
                                <StatusBadge :status="invoice.status" />
                            </span>
                        </li>
                    </ul>
                </template>
            </div>
        </div>

        <!-- Budget -->
        <div v-if="budget" class="rounded-lg bg-white p-6 shadow-sm" data-testid="budget-card">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Budget</h3>
                <SecondaryButton @click="openBudget()">Add Budget</SecondaryButton>
            </div>

            <template v-if="budget.rows.length">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <div class="text-sm text-gray-500">Total budget</div>
                        <div class="text-lg font-semibold text-gray-900">
                            {{ budget.budget !== null ? money(budget.budget) : money(budget.budget_base, budget.base_currency) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Consumed (billed + WIP)</div>
                        <div class="text-lg font-semibold text-gray-900">
                            {{ budget.budget !== null ? money(budget.consumed) : money(budget.consumed_base, budget.base_currency) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Utilisation</div>
                        <div
                            class="text-lg font-semibold"
                            :class="budget.utilisation > 100 ? 'text-red-600' : budget.utilisation > 75 ? 'text-amber-600' : 'text-green-700'"
                        >
                            {{ budget.utilisation }}%
                        </div>
                    </div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full"
                        :class="utilisationColor(budget.utilisation)"
                        :style="{ width: Math.min(budget.utilisation ?? 0, 100) + '%' }"
                    />
                </div>

                <h4 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Budget history
                </h4>
                <ul class="divide-y divide-gray-100 text-sm">
                    <li v-for="row in budget.rows" :key="row.id" class="flex items-center justify-between gap-3 py-2">
                        <span class="min-w-0">
                            <span class="font-medium text-gray-800">{{ money(row.amount, row.currency) }}</span>
                            <span v-if="row.description" class="ml-2 text-gray-600">{{ row.description }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ row.created_by }} · {{ shortDate(row.created_at) }}
                                <span v-if="row.amended">· amended</span>
                            </span>
                        </span>
                        <button class="shrink-0 text-xs text-indigo-600 hover:underline" @click="openBudget(row)">
                            Amend
                        </button>
                    </li>
                </ul>
            </template>
            <p v-else class="text-sm text-gray-500">
                No budget set — costs (billed + WIP) are untracked against a ceiling.
                Budgets accumulate: each addition raises the matter's total.
            </p>
        </div>

        <!-- Time -->
        <div class="rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between px-4 py-3">
                <h3 class="font-semibold text-gray-800">Time</h3>
                <SecondaryButton @click="showTime = true">Log Time</SecondaryButton>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">Timekeeper</th>
                            <th class="px-4 py-2.5">Code</th>
                            <th class="px-4 py-2.5">Narrative</th>
                            <th class="px-4 py-2.5 text-right">Time</th>
                            <th class="px-4 py-2.5 text-right">Rate</th>
                            <th class="px-4 py-2.5 text-right">Amount</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="entry in billing.timeEntries" :key="entry.id">
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ shortDate(entry.work_date) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-700">{{ entry.user?.name }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ entry.activity_code?.code ?? '—' }}</td>
                            <td class="max-w-xs truncate px-4 py-2.5 text-gray-600">{{ entry.narrative ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-700">
                                {{ entry.minutes }}m
                                <span v-if="entry.billed_minutes !== entry.minutes" class="text-xs text-gray-500">→ {{ entry.billed_minutes }}m</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-700">{{ money(entry.rate, entry.currency_code) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-gray-800">{{ money(entry.amount, entry.currency_code) }}</td>
                            <td class="px-4 py-2.5"><StatusBadge :status="entry.status" /></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-xs">
                                <template v-if="entry.status !== 'billed'">
                                    <button
                                        v-if="entry.status === 'billable'"
                                        class="text-gray-500 hover:underline"
                                        @click="writeOff('time-entries.status', entry)"
                                    >
                                        Write off
                                    </button>
                                    <button
                                        v-else
                                        class="text-indigo-600 hover:underline"
                                        @click="reinstate('time-entries.status', entry)"
                                    >
                                        Reinstate
                                    </button>
                                    <button class="ml-2 text-red-600 hover:underline" @click="removeItem('time-entries.destroy', entry, 'time entry')">
                                        Delete
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!billing.timeEntries.length">
                            <td colspan="9" class="px-4 py-6 text-center text-gray-500">No time recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Disbursements -->
        <div class="rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between px-4 py-3">
                <h3 class="font-semibold text-gray-800">Disbursements</h3>
                <SecondaryButton @click="showDisbursement = true">Add Disbursement</SecondaryButton>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">Description</th>
                            <th class="px-4 py-2.5">Supplier</th>
                            <th class="px-4 py-2.5 text-right">Cost</th>
                            <th class="px-4 py-2.5 text-right">Markup</th>
                            <th class="px-4 py-2.5 text-right">Billed</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in billing.disbursements" :key="item.id">
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ shortDate(item.date) }}</td>
                            <td class="max-w-xs truncate px-4 py-2.5 text-gray-700">{{ item.description }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ item.supplier ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-700">{{ money(item.cost_amount, item.cost_currency) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-600">{{ Number(item.markup_pct) }}%</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-gray-800">{{ money(item.amount, item.currency_code) }}</td>
                            <td class="px-4 py-2.5"><StatusBadge :status="item.status" /></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-xs">
                                <template v-if="item.status !== 'billed'">
                                    <button
                                        v-if="item.status === 'billable'"
                                        class="text-gray-500 hover:underline"
                                        @click="writeOff('disbursements.status', item)"
                                    >
                                        Write off
                                    </button>
                                    <button
                                        v-else
                                        class="text-indigo-600 hover:underline"
                                        @click="reinstate('disbursements.status', item)"
                                    >
                                        Reinstate
                                    </button>
                                    <button class="ml-2 text-red-600 hover:underline" @click="removeItem('disbursements.destroy', item, 'disbursement')">
                                        Delete
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!billing.disbursements.length">
                            <td colspan="8" class="px-4 py-6 text-center text-gray-500">No disbursements recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Charges -->
        <div class="rounded-lg bg-white shadow-sm">
            <div class="flex items-center justify-between px-4 py-3">
                <h3 class="font-semibold text-gray-800">Fixed Fees &amp; Charges</h3>
                <SecondaryButton @click="showCharge = true">Add Charge</SecondaryButton>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">Type</th>
                            <th class="px-4 py-2.5">Description</th>
                            <th class="px-4 py-2.5 text-right">Amount</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="charge in billing.charges" :key="charge.id">
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ shortDate(charge.date) }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5">
                                <StatusBadge :status="charge.type" :label="options.chargeTypes.find((t) => t.value === charge.type)?.label" />
                            </td>
                            <td class="max-w-md truncate px-4 py-2.5 text-gray-700">{{ charge.description }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-gray-800">{{ money(charge.amount, charge.currency_code) }}</td>
                            <td class="px-4 py-2.5"><StatusBadge :status="charge.status" /></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-xs">
                                <button
                                    v-if="charge.status !== 'billed'"
                                    class="text-red-600 hover:underline"
                                    @click="removeItem('charges.destroy', charge, 'charge')"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!billing.charges.length">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">No charges raised yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Budget modal -->
        <Modal :show="showBudget" @close="showBudget = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ editingBudget ? 'Amend Budget Entry' : 'Add Budget' }}
                </h3>
                <p v-if="!editingBudget" class="text-sm text-gray-600">
                    Budgets accumulate — this amount is added to the matter's total.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Amount" />
                        <TextInput v-model="budgetForm.amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="budgetForm.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Currency" />
                        <SelectInput v-model="budgetForm.currency_code" :options="options.currencies" class="mt-1" />
                        <InputError :message="budgetForm.errors.currency_code" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Description" />
                        <TextInput v-model="budgetForm.description" class="mt-1 w-full" placeholder="e.g. Prosecution phase budget" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showBudget = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="budgetForm.processing" @click="saveBudget">
                        {{ editingBudget ? 'Save Amendment' : 'Add Budget' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Agreement modal -->
        <Modal :show="showAgreement" max-width="2xl" @close="showAgreement = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Fee Agreement</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel value="Arrangement" />
                        <SelectInput v-model="agreementForm.type" :options="options.agreementTypes" class="mt-1" />
                        <InputError :message="agreementForm.errors.type" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Currency" />
                        <SelectInput
                            v-model="agreementForm.currency_code"
                            :options="options.currencies"
                            placeholder="Entity default"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Increment (min)" />
                        <TextInput v-model="agreementForm.increment_minutes" type="number" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.increment_minutes" class="mt-1" />
                    </div>
                    <div v-if="agreementForm.type === 'blended'">
                        <InputLabel value="Blended rate /h" />
                        <TextInput v-model="agreementForm.blended_rate" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.blended_rate" class="mt-1" />
                    </div>
                    <div v-if="agreementForm.type === 'capped'">
                        <InputLabel value="Fee cap" />
                        <TextInput v-model="agreementForm.cap_amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.cap_amount" class="mt-1" />
                    </div>
                    <div v-if="agreementForm.type === 'fixed'">
                        <InputLabel value="Fixed fee" />
                        <TextInput v-model="agreementForm.fixed_amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.fixed_amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Markup %" />
                        <TextInput v-model="agreementForm.default_markup_pct" type="number" step="0.01" class="mt-1 w-full" />
                    </div>
                </div>

                <div v-if="agreementForm.type === 'stage'" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <InputLabel value="Stage payments" />
                        <SecondaryButton type="button" @click="addStage">Add Stage</SecondaryButton>
                    </div>
                    <InputError :message="agreementForm.errors.stages" />
                    <div v-for="(stage, i) in agreementForm.stages" :key="i" class="flex gap-2">
                        <div class="flex-1">
                            <TextInput v-model="stage.description" placeholder="Milestone" class="w-full" />
                            <InputError :message="agreementForm.errors[`stages.${i}.description`]" class="mt-1" />
                        </div>
                        <div class="w-32">
                            <TextInput v-model="stage.amount" type="number" step="0.01" placeholder="Amount" class="w-full" />
                            <InputError :message="agreementForm.errors[`stages.${i}.amount`]" class="mt-1" />
                        </div>
                        <button type="button" class="text-red-600 hover:underline" @click="removeStage(i)">✕</button>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="agreementForm.requires_task_codes" type="checkbox" class="rounded text-indigo-600" />
                    Task-based billing — require a task code on every time entry
                </label>
                <div>
                    <InputLabel value="Notes" />
                    <TextareaInput v-model="agreementForm.notes" class="mt-1" rows="2" />
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showAgreement = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="agreementForm.processing" @click="saveAgreement">Save Agreement</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Log time modal -->
        <Modal :show="showTime" @close="showTime = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Log Time</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Timekeeper" />
                        <SelectInput v-model="timeForm.user_id" :options="userOptions" placeholder="Select…" class="mt-1" />
                        <InputError :message="timeForm.errors.user_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Date" />
                        <DateInput v-model="timeForm.work_date" class="mt-1" />
                        <InputError :message="timeForm.errors.work_date" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Minutes worked" />
                        <TextInput v-model="timeForm.minutes" type="number" class="mt-1 w-full" />
                        <InputError :message="timeForm.errors.minutes" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-500">
                            Billed in {{ agreement?.increment_minutes ?? 6 }}-minute increments.
                        </p>
                    </div>
                    <div>
                        <InputLabel :value="agreement?.requires_task_codes ? 'Task code *' : 'Task code'" />
                        <SelectInput
                            v-model="timeForm.activity_code_id"
                            :options="options.activityCodes"
                            placeholder="—"
                            class="mt-1"
                        />
                        <InputError :message="timeForm.errors.activity_code_id" class="mt-1" />
                    </div>
                </div>
                <div>
                    <InputLabel value="Narrative" />
                    <TextareaInput v-model="timeForm.narrative" class="mt-1" rows="2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input
                        :checked="timeForm.status === 'non_billable'"
                        type="checkbox"
                        class="rounded text-indigo-600"
                        @change="timeForm.status = $event.target.checked ? 'non_billable' : 'billable'"
                    />
                    Non-billable
                </label>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showTime = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="timeForm.processing" @click="logTime">Log Time</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Disbursement modal -->
        <Modal :show="showDisbursement" @close="showDisbursement = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Add Disbursement</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Date" />
                        <DateInput v-model="disbursementForm.date" class="mt-1" />
                        <InputError :message="disbursementForm.errors.date" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Supplier" />
                        <TextInput v-model="disbursementForm.supplier" class="mt-1 w-full" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Description" />
                        <TextInput v-model="disbursementForm.description" class="mt-1 w-full" placeholder="e.g. EPO examination fee" />
                        <InputError :message="disbursementForm.errors.description" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Cost amount" />
                        <TextInput v-model="disbursementForm.cost_amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="disbursementForm.errors.cost_amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Cost currency" />
                        <SelectInput v-model="disbursementForm.cost_currency" :options="options.currencies" class="mt-1" />
                        <InputError :message="disbursementForm.errors.cost_currency" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Markup %" />
                        <TextInput
                            v-model="disbursementForm.markup_pct"
                            type="number"
                            step="0.01"
                            :placeholder="`Agreement default (${agreement?.default_markup_pct ?? 0}%)`"
                            class="mt-1 w-full"
                        />
                        <InputError :message="disbursementForm.errors.markup_pct" class="mt-1" />
                    </div>
                </div>
                <p class="text-xs text-gray-500">
                    The cost is marked up and converted into the billing currency ({{ billing.currency }}) at the stored exchange rate.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showDisbursement = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="disbursementForm.processing" @click="addDisbursement">Add Disbursement</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Charge modal -->
        <Modal :show="showCharge" @close="showCharge = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Add Charge</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Type" />
                        <SelectInput v-model="chargeForm.type" :options="options.chargeTypes" class="mt-1" />
                        <InputError :message="chargeForm.errors.type" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Date" />
                        <DateInput v-model="chargeForm.date" class="mt-1" />
                        <InputError :message="chargeForm.errors.date" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Description" />
                        <TextInput v-model="chargeForm.description" class="mt-1 w-full" />
                        <InputError :message="chargeForm.errors.description" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="`Amount (${billing.currency})`" />
                        <TextInput v-model="chargeForm.amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="chargeForm.errors.amount" class="mt-1" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showCharge = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="chargeForm.processing" @click="addCharge">Add Charge</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Draft invoice modal -->
        <Modal :show="showInvoice" @close="showInvoice = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Draft Invoice</h3>
                <p class="text-sm text-gray-600">
                    Unbilled items are gathered onto a draft invoice for the billing entity.
                    Nothing is final until the invoice is issued.
                </p>
                <div class="space-y-2 text-sm text-gray-700">
                    <label class="flex items-center gap-2">
                        <input v-model="invoiceForm.include_time" type="checkbox" class="rounded text-indigo-600" />
                        Time ({{ money(billing.wip.time) }})
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="invoiceForm.include_disbursements" type="checkbox" class="rounded text-indigo-600" />
                        Disbursements ({{ money(billing.wip.disbursements) }})
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="invoiceForm.include_charges" type="checkbox" class="rounded text-indigo-600" />
                        Fixed fees &amp; charges ({{ money(billing.wip.charges) }})
                    </label>
                </div>
                <div>
                    <InputLabel value="Only items up to (optional)" />
                    <DateInput v-model="invoiceForm.through" class="mt-1" />
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showInvoice = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="invoiceForm.processing" @click="draftInvoice">Create Draft</PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
