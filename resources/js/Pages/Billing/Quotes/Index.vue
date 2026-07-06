<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, reactive, watch } from 'vue';

const props = defineProps({
    quotes: Object,
    filters: Object,
    statuses: Array,
    clients: Array,
});

const form = reactive({
    status: props.filters.status ?? '',
    client_id: props.filters.client_id ?? '',
});

const pruned = () =>
    Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('quotes.index'), pruned(), { preserveState: true, replace: true });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const onPage = (event) => {
    router.get(
        route('quotes.index'),
        { ...pruned(), page: event.page + 1 },
        { preserveState: true, replace: true }
    );
};

const money = (amount, currency) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount ?? 0);
</script>

<template>
    <Head title="Quotes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Quotes</h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('invoices.index')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Invoices
                    </Link>
                    <Link
                        :href="route('quotes.create')"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        New Quote
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <SelectInput v-model="form.status" :options="statuses" placeholder="All statuses" />
                <SelectInput
                    v-model="form.client_id"
                    :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                    placeholder="All clients"
                />
            </div>

            <DataTable
                :value="quotes.data"
                lazy
                paginator
                :rows="quotes.per_page"
                :total-records="quotes.total"
                :first="(quotes.current_page - 1) * quotes.per_page"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
                @page="onPage"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">No quotes yet.</p>
                </template>

                <Column header="Quote">
                    <template #body="{ data }">
                        <Link :href="route('quotes.edit', data.id)" class="whitespace-nowrap font-medium text-indigo-600 hover:underline">
                            {{ data.quote_no }}
                        </Link>
                    </template>
                </Column>
                <Column header="Client / Entity">
                    <template #body="{ data }">
                        <span class="block max-w-[16rem] truncate text-gray-700">
                            {{ data.client?.name }}
                            <span v-if="data.entity" class="text-gray-500">— {{ data.entity.name }}</span>
                        </span>
                    </template>
                </Column>
                <Column header="Matter">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-600">{{ data.matter?.reference ?? '—' }}</span>
                    </template>
                </Column>
                <Column header="Total">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap font-medium text-gray-800">{{ money(data.total, data.currency_code) }}</span>
                    </template>
                </Column>
                <Column header="Status">
                    <template #body="{ data }">
                        <StatusBadge :status="data.status" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
