<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    rows: Array,
    filters: Object,
    clients: Array,
    users: Array,
    currencies: Array,
    baseCurrency: String,
});

const form = reactive({
    user_id: props.filters.user_id ?? '',
    client_id: props.filters.client_id ?? '',
});

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        // user_id is always sent (even empty) so "all attorneys" is
        // distinguishable from the my-portfolio default.
        router.get(
            route('budgets.index'),
            { user_id: form.user_id, ...(form.client_id ? { client_id: form.client_id } : {}) },
            { preserveState: true, replace: true }
        );
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const money = (amount, currency) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount ?? 0);

const ragStatus = (pct) =>
    pct === null ? 'draft' : pct > 100 ? 'critical' : pct > 75 ? 'pending' : 'completed';

const barColor = (pct) =>
    pct === null ? 'bg-gray-300' : pct > 100 ? 'bg-red-500' : pct > 75 ? 'bg-amber-500' : 'bg-green-500';

// --- add budget from the dashboard ---
const budgetFor = ref(null); // row being topped up

const budgetForm = useForm({ amount: '', currency_code: '', description: '' });

const openBudget = (row) => {
    budgetFor.value = row;
    budgetForm.defaults({ amount: '', currency_code: row.currency, description: '' });
    budgetForm.reset();
    budgetForm.clearErrors();
};

const saveBudget = () =>
    budgetForm
        .transform((d) => ({ ...d, description: d.description || null }))
        .post(route('matters.budgets.store', budgetFor.value.id), {
            preserveScroll: true,
            onSuccess: () => (budgetFor.value = null),
        });
</script>

<template>
    <Head title="Budgets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Budgets</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Budget vs cost (billed + WIP) across your portfolio —
                        each budget entry records who added it, when, and in
                        what currency.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('billing.wip')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        WIP
                    </Link>
                    <Link
                        :href="route('invoices.index')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Invoices
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <SelectInput
                    v-model="form.user_id"
                    :options="users.map((u) => ({ value: u.id, label: u.name }))"
                    placeholder="All attorneys"
                />
                <SelectInput
                    v-model="form.client_id"
                    :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                    placeholder="All clients"
                />
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Matter</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Attorney</th>
                            <th class="px-4 py-3 text-right">Budget</th>
                            <th class="px-4 py-3 text-right">Consumed</th>
                            <th class="px-4 py-3 text-right">Remaining</th>
                            <th class="px-4 py-3">Utilisation</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in rows" :key="row.id" :data-testid="`budget-row-${row.reference}`">
                            <td class="px-4 py-3">
                                <Link :href="route('matters.show', row.id)" class="whitespace-nowrap font-medium text-indigo-600 hover:underline">
                                    {{ row.reference }}
                                </Link>
                                <span class="ml-2 hidden max-w-[14rem] truncate text-gray-500 xl:inline">{{ row.title }}</span>
                            </td>
                            <td class="max-w-[12rem] truncate px-4 py-3 text-gray-600">{{ row.client_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ row.attorney ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-gray-800">
                                {{ row.budget !== null ? money(row.budget, row.currency) : money(row.budget_base, row.base_currency) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-gray-700">
                                {{ row.budget !== null ? money(row.consumed, row.currency) : money(row.consumed_base, row.base_currency) }}
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 text-right font-medium"
                                :class="(row.budget !== null ? row.budget - row.consumed : row.budget_base - row.consumed_base) < 0 ? 'text-red-600' : 'text-gray-800'"
                            >
                                {{
                                    row.budget !== null
                                        ? money(row.budget - row.consumed, row.currency)
                                        : money(row.budget_base - row.consumed_base, row.base_currency)
                                }}
                            </td>
                            <td class="min-w-[10rem] px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-100">
                                        <div
                                            class="h-full rounded-full"
                                            :class="barColor(row.utilisation)"
                                            :style="{ width: Math.min(row.utilisation ?? 0, 100) + '%' }"
                                        />
                                    </div>
                                    <StatusBadge :status="ragStatus(row.utilisation)" :label="`${row.utilisation ?? 0}%`" />
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                <button class="text-indigo-600 hover:underline" @click="openBudget(row)">
                                    Add budget
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                No budgeted matters in this view — set a budget from a
                                matter's Billing tab, or clear the attorney filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add budget modal -->
        <Modal :show="budgetFor !== null" @close="budgetFor = null">
            <div v-if="budgetFor" class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">
                    Add Budget — {{ budgetFor.reference }}
                </h3>
                <p class="text-sm text-gray-600">
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
                        <SelectInput v-model="budgetForm.currency_code" :options="currencies" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Description" />
                        <TextInput v-model="budgetForm.description" class="mt-1 w-full" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="budgetFor = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="budgetForm.processing" @click="saveBudget">Add Budget</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
