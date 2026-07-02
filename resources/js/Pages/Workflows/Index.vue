<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

defineProps({
    workflows: Array,
});

const confirmDelete = useDeleteConfirm();

const destroy = (workflow) =>
    confirmDelete(`Delete workflow “${workflow.name}”? Existing tasks are kept.`, () =>
        router.delete(route('workflows.destroy', workflow.id)));
</script>

<template>
    <Head title="Workflows" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Workflows</h2>
                <Link
                    :href="route('workflows.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    New Workflow
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <DataTable
                :value="workflows"
                data-key="id"
                size="small"
                sort-field="name"
                :sort-order="1"
                class="overflow-hidden rounded-lg shadow-sm"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">
                        No workflows yet — create deadline chains for filings, office actions, renewals and more.
                    </p>
                </template>

                <Column field="name" header="Name" sortable>
                    <template #body="{ data }">
                        <Link
                            :href="route('workflows.edit', data.id)"
                            class="font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.name }}
                        </Link>
                        <div v-if="data.description" class="max-w-md truncate text-xs text-gray-500">
                            {{ data.description }}
                        </div>
                    </template>
                </Column>
                <Column field="matter_type" header="Matter type" sortable>
                    <template #body="{ data }">
                        <span class="capitalize text-gray-600">{{ data.matter_type ?? 'Any' }}</span>
                    </template>
                </Column>
                <Column field="trigger_event" header="Trigger" sortable>
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.trigger_event.replace('_', ' ') }}</span>
                    </template>
                </Column>
                <Column field="steps_count" header="Steps" sortable class="text-right" />
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
                                :href="route('workflows.edit', data.id)"
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
