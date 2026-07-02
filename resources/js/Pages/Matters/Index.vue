<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, reactive, watch } from 'vue';

const props = defineProps({
    matters: Object,
    filters: Object,
    types: Array,
    statuses: Array,
    countries: Array,
    clients: Array,
});

const form = reactive({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    country: props.filters.country ?? '',
    client_id: props.filters.client_id ?? '',
});

const pruned = () =>
    Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('matters.index'), pruned(), {
            preserveState: true,
            replace: true,
        });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const onPage = (event) => {
    router.get(
        route('matters.index'),
        { ...pruned(), page: event.page + 1 },
        { preserveState: true, replace: true }
    );
};

const typeLabel = (value) => props.types.find((t) => t.value === value)?.label ?? value;
</script>

<template>
    <Head title="Matters" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Matters
                </h2>
                <Link
                    :href="route('matters.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    New Matter
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <input
                    v-model="form.search"
                    type="search"
                    placeholder="Search ref, title, number, client…"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <SelectInput v-model="form.type" :options="types" placeholder="All types" />
                <SelectInput v-model="form.status" :options="statuses" placeholder="All statuses" />
                <SelectInput v-model="form.country" :options="countries" placeholder="All jurisdictions" />
                <SelectInput
                    v-model="form.client_id"
                    :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                    placeholder="All clients"
                />
            </div>

            <DataTable
                :value="matters.data"
                lazy
                paginator
                :rows="matters.per_page"
                :total-records="matters.total"
                :first="(matters.current_page - 1) * matters.per_page"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
                @page="onPage"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">No matters match your filters.</p>
                </template>

                <Column header="Reference">
                    <template #body="{ data }">
                        <Link
                            :href="route('matters.show', data.id)"
                            class="whitespace-nowrap font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.reference }}
                        </Link>
                    </template>
                </Column>
                <Column header="Title">
                    <template #body="{ data }">
                        <span class="block max-w-xs truncate text-gray-700">{{ data.title }}</span>
                    </template>
                </Column>
                <Column header="Type">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-600">{{ typeLabel(data.matter_type) }}</span>
                    </template>
                </Column>
                <Column field="country_code" header="Ctry" />
                <Column header="Client">
                    <template #body="{ data }">
                        <span class="block max-w-[12rem] truncate text-gray-600">{{ data.client?.name }}</span>
                    </template>
                </Column>
                <Column header="App. No">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-600">{{ data.application_no ?? '—' }}</span>
                    </template>
                </Column>
                <Column header="Status">
                    <template #body="{ data }">
                        <StatusBadge :status="data.status" />
                    </template>
                </Column>
                <Column header="Attorney">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-600">{{ data.responsible_user?.name ?? '—' }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
