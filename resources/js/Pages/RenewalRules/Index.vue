<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

defineProps({
    rules: Array,
});

const confirmDelete = useDeleteConfirm();

const destroy = (rule) =>
    confirmDelete(`Delete rule “${rule.name}”? Existing renewals are kept.`, () =>
        router.delete(route('renewal-rules.destroy', rule.id)));
</script>

<template>
    <Head title="Renewal Schedule Rules" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Renewal Schedule Rules
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Templates the scheduler uses to generate renewals — a
                        country-specific rule overrides the type-wide default.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('renewals.index')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Back to Renewals
                    </Link>
                    <Link
                        :href="route('renewal-rules.create')"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        New Rule
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <DataTable
                :value="rules"
                data-key="id"
                size="small"
                class="overflow-hidden rounded-lg shadow-sm"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">
                        No rules configured — the scheduler cannot generate renewals without them.
                    </p>
                </template>

                <Column field="name" header="Rule" sortable>
                    <template #body="{ data }">
                        <Link
                            :href="route('renewal-rules.edit', data.id)"
                            class="font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.name }}
                        </Link>
                        <div v-if="data.notes" class="mt-0.5 max-w-md truncate text-xs text-gray-500">
                            {{ data.notes }}
                        </div>
                    </template>
                </Column>
                <Column field="matter_type" header="Matter type" sortable>
                    <template #body="{ data }">
                        <span class="capitalize text-gray-600">{{ data.matter_type }}</span>
                    </template>
                </Column>
                <Column field="country_code" header="Jurisdiction" sortable>
                    <template #body="{ data }">
                        <template v-if="data.country_code">
                            {{ data.country_code }}
                            <span class="text-xs text-gray-400"> — {{ data.country_name }}</span>
                        </template>
                        <span
                            v-else
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                        >
                            Any (default)
                        </span>
                    </template>
                </Column>
                <Column header="Schedule">
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.summary }}</span>
                    </template>
                </Column>
                <Column field="grace_months" header="Grace" sortable>
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.grace_months }} mo</span>
                    </template>
                </Column>
                <Column field="is_active" header="Active" sortable>
                    <template #body="{ data }">
                        <StatusBadge
                            :status="data.is_active ? 'completed' : 'cancelled'"
                            :label="data.is_active ? 'Active' : 'Inactive'"
                        />
                    </template>
                </Column>
                <Column>
                    <template #body="{ data }">
                        <div class="whitespace-nowrap text-right text-xs">
                            <Link
                                :href="route('renewal-rules.edit', data.id)"
                                class="text-indigo-600 hover:underline"
                            >
                                Edit
                            </Link>
                            <button class="ml-3 text-red-600 hover:underline" @click="destroy(data)">
                                Delete
                            </button>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
