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
    tasks: Object,
    filters: Object,
    statuses: Array,
});

const form = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'open',
    assignee: props.filters.assignee ?? '',
    overdue: Boolean(props.filters.overdue),
});

const params = () => {
    const p = {};
    if (form.search) p.search = form.search;
    if (form.status && form.status !== 'open') p.status = form.status;
    if (form.assignee) p.assignee = form.assignee;
    if (form.overdue) p.overdue = 1;
    return p;
};

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('tasks.index'), params(), { preserveState: true, replace: true });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const onPage = (event) => {
    router.get(
        route('tasks.index'),
        { ...params(), page: event.page + 1 },
        { preserveState: true, replace: true }
    );
};

const setStatus = (task, status) =>
    router.patch(route('tasks.update', task.id), { status }, { preserveScroll: true });
</script>

<template>
    <Head title="Tasks" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Tasks &amp; Actions
            </h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                <input
                    v-model="form.search"
                    type="search"
                    placeholder="Search task or matter ref…"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64"
                />
                <SelectInput
                    v-model="form.status"
                    :options="[{ value: 'open', label: 'All Open' }, ...statuses]"
                    class="!w-40"
                />
                <SelectInput
                    v-model="form.assignee"
                    :options="[{ value: 'me', label: 'Assigned to me' }]"
                    placeholder="Everyone"
                    class="!w-44"
                />
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.overdue" type="checkbox" class="rounded text-indigo-600" />
                    Overdue only
                </label>
            </div>

            <DataTable
                :value="tasks.data"
                lazy
                paginator
                :rows="tasks.per_page"
                :total-records="tasks.total"
                :first="(tasks.current_page - 1) * tasks.per_page"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
                @page="onPage"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">No tasks match your filters.</p>
                </template>

                <Column header="Task">
                    <template #body="{ data }">
                        <span class="font-medium text-gray-800">{{ data.title }}</span>
                        <span
                            v-if="data.is_critical"
                            class="ml-1 text-xs font-semibold text-red-600"
                            title="Statutory / critical deadline"
                            >⚠</span
                        >
                    </template>
                </Column>
                <Column header="Matter">
                    <template #body="{ data }">
                        <Link
                            v-if="data.matter"
                            :href="route('matters.show', data.matter.id)"
                            class="whitespace-nowrap text-indigo-600 hover:underline"
                        >
                            {{ data.matter.reference }}
                        </Link>
                        <span v-else>—</span>
                    </template>
                </Column>
                <Column header="Due">
                    <template #body="{ data }">
                        <DueDate
                            :date="data.due_date"
                            :highlight="['pending', 'in_progress'].includes(data.status)"
                            class="whitespace-nowrap"
                        />
                    </template>
                </Column>
                <Column header="Priority">
                    <template #body="{ data }">
                        <StatusBadge :status="data.priority" />
                    </template>
                </Column>
                <Column header="Assignee">
                    <template #body="{ data }">
                        <span class="whitespace-nowrap text-gray-600">{{ data.assignee?.name ?? '—' }}</span>
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
                            <template v-if="['pending', 'in_progress'].includes(data.status)">
                                <button
                                    v-if="data.status === 'pending'"
                                    class="text-indigo-600 hover:underline"
                                    @click="setStatus(data, 'in_progress')"
                                >
                                    Start
                                </button>
                                <button
                                    class="ml-2 text-green-700 hover:underline"
                                    @click="setStatus(data, 'completed')"
                                >
                                    Complete
                                </button>
                            </template>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
