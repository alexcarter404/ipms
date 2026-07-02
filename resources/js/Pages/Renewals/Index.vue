<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DueDate from '@/Components/DueDate.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, reactive, watch } from 'vue';

const props = defineProps({
    renewals: Object,
    filters: Object,
    statuses: Array,
});

const form = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'open',
    due_within: props.filters.due_within ?? '',
});

const params = () => {
    const p = {};
    if (form.search) p.search = form.search;
    if (form.status && form.status !== 'open') p.status = form.status;
    if (form.due_within) p.due_within = form.due_within;
    return p;
};

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('renewals.index'), params(), { preserveState: true, replace: true });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const onPage = (event) => {
    router.get(
        route('renewals.index'),
        { ...params(), page: event.page + 1 },
        { preserveState: true, replace: true }
    );
};

const setStatus = (renewal, status) =>
    router.patch(route('renewals.update', renewal.id), { status }, { preserveScroll: true });

const isOpen = (renewal) =>
    ['upcoming', 'reminder_sent', 'instructed'].includes(renewal.status);
</script>

<template>
    <Head title="Renewals" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Renewals</h2>
                <Link
                    :href="route('renewal-rules.index')"
                    class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                >
                    Schedule Rules
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                <input
                    v-model="form.search"
                    type="search"
                    placeholder="Search matter ref or title…"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64"
                />
                <SelectInput
                    v-model="form.status"
                    :options="[{ value: 'open', label: 'All Open' }, ...statuses]"
                    class="!w-44"
                />
                <SelectInput
                    v-model="form.due_within"
                    :options="[
                        { value: '30', label: 'Due within 30 days' },
                        { value: '90', label: 'Due within 90 days' },
                        { value: '180', label: 'Due within 180 days' },
                        { value: '365', label: 'Due within 1 year' },
                    ]"
                    placeholder="Any due date"
                    class="!w-48"
                />
            </div>

            <DataTable
                :value="renewals.data"
                lazy
                paginator
                :rows="renewals.per_page"
                :total-records="renewals.total"
                :first="(renewals.current_page - 1) * renewals.per_page"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
                @page="onPage"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">No renewals match your filters.</p>
                </template>

                <Column header="Matter">
                    <template #body="{ data }">
                        <Link
                            v-if="data.matter"
                            :href="route('matters.show', data.matter.id)"
                            class="whitespace-nowrap font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.matter.reference }}
                        </Link>
                        <span class="block max-w-[16rem] truncate text-xs text-gray-500">
                            {{ data.matter?.title }}
                        </span>
                    </template>
                </Column>
                <Column header="Client">
                    <template #body="{ data }">
                        <span class="block max-w-[10rem] truncate text-gray-600">
                            {{ data.matter?.client?.name ?? '—' }}
                        </span>
                    </template>
                </Column>
                <Column header="Type">
                    <template #body="{ data }">
                        <span class="capitalize text-gray-600">{{ data.matter?.matter_type }}</span>
                    </template>
                </Column>
                <Column header="Ctry">
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.matter?.country_code }}</span>
                    </template>
                </Column>
                <Column field="cycle" header="Cycle" />
                <Column header="Due">
                    <template #body="{ data }">
                        <DueDate :date="data.due_date" :highlight="isOpen(data)" class="whitespace-nowrap" />
                    </template>
                </Column>
                <Column header="Status">
                    <template #body="{ data }">
                        <StatusBadge :status="data.status" />
                    </template>
                </Column>
                <Column>
                    <template #body="{ data }">
                        <div class="whitespace-nowrap text-right text-xs">
                            <template v-if="isOpen(data)">
                                <button
                                    v-if="data.status !== 'instructed'"
                                    class="text-indigo-600 hover:underline"
                                    @click="setStatus(data, 'instructed')"
                                >
                                    Instructed
                                </button>
                                <button
                                    class="ml-2 text-green-700 hover:underline"
                                    @click="setStatus(data, 'paid')"
                                >
                                    Paid
                                </button>
                            </template>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
