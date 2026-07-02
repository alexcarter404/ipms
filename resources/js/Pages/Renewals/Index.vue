<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DueDate from '@/Components/DueDate.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

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

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        const params = {};
        if (form.search) params.search = form.search;
        if (form.status && form.status !== 'open') params.status = form.status;
        if (form.due_within) params.due_within = form.due_within;
        router.get(route('renewals.index'), params, { preserveState: true, replace: true });
    }, 300);
});

const setStatus = (renewal, status) =>
    router.patch(route('renewals.update', renewal.id), { status }, { preserveScroll: true });
</script>

<template>
    <Head title="Renewals" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Renewals</h2>
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

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Matter</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Ctry</th>
                            <th class="px-4 py-3">Cycle</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="renewal in renewals.data" :key="renewal.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <Link
                                    v-if="renewal.matter"
                                    :href="route('matters.show', renewal.matter.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ renewal.matter.reference }}
                                </Link>
                                <div class="max-w-[16rem] truncate text-xs text-gray-500">
                                    {{ renewal.matter?.title }}
                                </div>
                            </td>
                            <td class="max-w-[10rem] truncate px-4 py-3 text-gray-600">
                                {{ renewal.matter?.client?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 capitalize text-gray-600">
                                {{ renewal.matter?.matter_type }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ renewal.matter?.country_code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ renewal.cycle }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <DueDate
                                    :date="renewal.due_date"
                                    :highlight="['upcoming', 'reminder_sent', 'instructed'].includes(renewal.status)"
                                />
                            </td>
                            <td class="px-4 py-3"><StatusBadge :status="renewal.status" /></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                <template v-if="['upcoming', 'reminder_sent', 'instructed'].includes(renewal.status)">
                                    <button
                                        v-if="renewal.status !== 'instructed'"
                                        class="text-indigo-600 hover:underline"
                                        @click="setStatus(renewal, 'instructed')"
                                    >
                                        Instructed
                                    </button>
                                    <button
                                        class="ml-2 text-green-700 hover:underline"
                                        @click="setStatus(renewal, 'paid')"
                                    >
                                        Paid
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!renewals.data.length">
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                No renewals match your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="renewals.links" />
        </div>
    </AuthenticatedLayout>
</template>
