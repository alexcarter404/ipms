<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, reactive, watch } from 'vue';

const props = defineProps({
    rows: Array,
    baseCurrency: String,
    firmTotal: Number,
    filters: Object,
    clients: Array,
    users: Array,
});

const form = reactive({
    client_id: props.filters.client_id ?? '',
    user_id: props.filters.user_id ?? '',
});

const pruned = () =>
    Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('billing.wip'), pruned(), { preserveState: true, replace: true });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const money = (amount, currency) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount ?? 0);

// Age of the oldest unbilled item: fresh, ageing, or overdue attention.
const ageSeverity = (days) => (days > 90 ? 'critical' : days > 30 ? 'pending' : 'completed');
const ageLabel = (days) => (days === 0 ? 'Today' : days === 1 ? '1 day' : `${days} days`);
</script>

<template>
    <Head title="Work in Progress" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Work in Progress
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Unbilled totals by the entity that gets the bill — drill in
                        to review items, amend descriptions and raise bills.
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right" data-testid="firm-wip-total">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Firm WIP ({{ baseCurrency }})</div>
                        <div class="text-lg font-semibold text-gray-900">{{ money(firmTotal, baseCurrency) }}</div>
                    </div>
                    <Link
                        :href="route('budgets.index')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Budgets
                    </Link>
                    <Link
                        :href="route('invoices.index')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Invoices
                    </Link>
                    <Link
                        :href="route('billing.settings')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Billing Settings
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <SelectInput
                    v-model="form.client_id"
                    :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                    placeholder="All clients"
                />
                <SelectInput
                    v-model="form.user_id"
                    :options="users.map((u) => ({ value: u.id, label: u.name }))"
                    placeholder="All attorneys"
                />
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Billing entity</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3 text-right">Matters</th>
                            <th class="px-4 py-3">Oldest WIP</th>
                            <th class="px-4 py-3 text-right">Unbilled total</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in rows" :key="row.entity.id">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('billing.wip.show', row.entity.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ row.entity.name }}
                                </Link>
                            </td>
                            <td class="max-w-[16rem] truncate px-4 py-3 text-gray-600">{{ row.entity.client_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-gray-700">{{ row.matter_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <StatusBadge :status="ageSeverity(row.oldest_days)" :label="ageLabel(row.oldest_days)" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-gray-900">
                                {{ money(row.total, row.entity.currency) }}
                                <span v-if="row.entity.currency !== baseCurrency" class="block text-xs font-normal text-gray-500">
                                    ≈ {{ money(row.base_total, baseCurrency) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <Link
                                    :href="route('billing.wip.show', row.entity.id)"
                                    class="text-sm font-medium text-indigo-600 hover:underline"
                                >
                                    Review &amp; bill →
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                No unbilled work in progress — log time, disbursements or
                                charges on a matter's Billing tab and it will appear here.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
