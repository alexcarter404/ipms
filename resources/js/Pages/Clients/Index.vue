<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    clients: Object,
    filters: Object,
});

const search = ref(props.filters.search ?? '');

const params = () => (search.value ? { search: search.value } : {});

let timeout = null;
watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('clients.index'), params(), {
            preserveState: true,
            replace: true,
        });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const onPage = (event) => {
    router.get(
        route('clients.index'),
        { ...params(), page: event.page + 1 },
        { preserveState: true, replace: true }
    );
};
</script>

<template>
    <Head title="Clients" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Clients</h2>
                <Link
                    :href="route('clients.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    New Client
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search name or code…"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-80"
                />
            </div>

            <DataTable
                :value="clients.data"
                lazy
                paginator
                :rows="clients.per_page"
                :total-records="clients.total"
                :first="(clients.current_page - 1) * clients.per_page"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
                @page="onPage"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">No clients found.</p>
                </template>

                <Column header="Code">
                    <template #body="{ data }">
                        <Link
                            :href="route('clients.show', data.id)"
                            class="whitespace-nowrap font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.code }}
                        </Link>
                    </template>
                </Column>
                <Column field="name" header="Name" />
                <Column header="Type">
                    <template #body="{ data }">
                        <span class="capitalize text-gray-600">{{ data.type }}</span>
                    </template>
                </Column>
                <Column header="Country">
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.country_code ?? '—' }}</span>
                    </template>
                </Column>
                <Column header="Email">
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.email ?? '—' }}</span>
                    </template>
                </Column>
                <Column header="Matters" class="text-right">
                    <template #body="{ data }">
                        <span class="font-medium text-gray-800">{{ data.matters_count }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
