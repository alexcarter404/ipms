<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

defineProps({
    templates: Array,
});

const confirmDelete = useDeleteConfirm();

const destroy = (template) =>
    confirmDelete(`Delete template “${template.name}”?`, () =>
        router.delete(route('templates.destroy', template.id)));
</script>

<template>
    <Head title="Communication Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Communication Templates
                </h2>
                <Link
                    :href="route('templates.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    New Template
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <DataTable
                :value="templates"
                data-key="id"
                size="small"
                sort-field="name"
                :sort-order="1"
                class="overflow-hidden rounded-lg shadow-sm"
            >
                <template #empty>
                    <p class="py-4 text-center text-gray-500">
                        No templates yet — create reusable letters and emails with merge fields.
                    </p>
                </template>

                <Column field="name" header="Name" sortable>
                    <template #body="{ data }">
                        <Link
                            :href="route('templates.edit', data.id)"
                            class="font-medium text-indigo-600 hover:underline"
                        >
                            {{ data.name }}
                        </Link>
                        <div v-if="data.subject" class="max-w-md truncate text-xs text-gray-500">
                            {{ data.subject }}
                        </div>
                    </template>
                </Column>
                <Column field="channel" header="Channel" sortable>
                    <template #body="{ data }">
                        <span class="capitalize text-gray-600">{{ data.channel }}</span>
                    </template>
                </Column>
                <Column field="matter_type" header="Matter type" sortable>
                    <template #body="{ data }">
                        <span class="capitalize text-gray-600">{{ data.matter_type ?? 'Any' }}</span>
                    </template>
                </Column>
                <Column field="communications_count" header="Used" sortable class="text-right" />
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
                                :href="route('templates.edit', data.id)"
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
