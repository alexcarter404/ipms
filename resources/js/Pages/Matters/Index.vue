<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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

const pruned = () =>
    Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));

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

            <!-- Table -->
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Ctry</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">App. No</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Attorney</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="matter in matters.data"
                            :key="matter.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-4 py-3">
                                <Link
                                    :href="route('matters.show', matter.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ matter.reference }}
                                </Link>
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-gray-700">
                                {{ matter.title }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ typeLabel(matter.matter_type) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ matter.country_code }}</td>
                            <td class="max-w-[12rem] truncate px-4 py-3 text-gray-600">
                                {{ matter.client?.name }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ matter.application_no ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="matter.status" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ matter.responsible_user?.name ?? '—' }}
                            </td>
                        </tr>
                        <tr v-if="!matters.data.length">
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                No matters match your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="matters.links" />
        </div>
    </AuthenticatedLayout>
</template>
