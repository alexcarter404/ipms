<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DueDate from '@/Components/DueDate.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        const params = {};
        if (form.search) params.search = form.search;
        if (form.status && form.status !== 'open') params.status = form.status;
        if (form.assignee) params.assignee = form.assignee;
        if (form.overdue) params.overdue = 1;
        router.get(route('tasks.index'), params, { preserveState: true, replace: true });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

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

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Task</th>
                            <th class="px-4 py-3">Matter</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Assignee</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="task in tasks.data" :key="task.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-800">{{ task.title }}</span>
                                <span
                                    v-if="task.is_critical"
                                    class="ml-1 text-xs font-semibold text-red-600"
                                    title="Statutory / critical deadline"
                                    >⚠</span
                                >
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <Link
                                    v-if="task.matter"
                                    :href="route('matters.show', task.matter.id)"
                                    class="text-indigo-600 hover:underline"
                                >
                                    {{ task.matter.reference }}
                                </Link>
                                <span v-else>—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <DueDate
                                    :date="task.due_date"
                                    :highlight="['pending', 'in_progress'].includes(task.status)"
                                />
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="task.priority" /></td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ task.assignee?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="task.status" /></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                <template v-if="['pending', 'in_progress'].includes(task.status)">
                                    <button
                                        v-if="task.status === 'pending'"
                                        class="text-indigo-600 hover:underline"
                                        @click="setStatus(task, 'in_progress')"
                                    >
                                        Start
                                    </button>
                                    <button
                                        class="ml-2 text-green-700 hover:underline"
                                        @click="setStatus(task, 'completed')"
                                    >
                                        Complete
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!tasks.data.length">
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No tasks match your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="tasks.links" />
        </div>
    </AuthenticatedLayout>
</template>
