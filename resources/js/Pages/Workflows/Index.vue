<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    workflows: Array,
});

const destroy = (workflow) => {
    if (!confirm(`Delete workflow “${workflow.name}”? Existing tasks are kept.`)) return;
    router.delete(route('workflows.destroy', workflow.id));
};
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
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Matter type</th>
                            <th class="px-4 py-3">Trigger</th>
                            <th class="px-4 py-3 text-right">Steps</th>
                            <th class="px-4 py-3">Active</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="workflow in workflows" :key="workflow.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('workflows.edit', workflow.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ workflow.name }}
                                </Link>
                                <div v-if="workflow.description" class="max-w-md truncate text-xs text-gray-500">
                                    {{ workflow.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3 capitalize text-gray-600">
                                {{ workflow.matter_type ?? 'Any' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ workflow.trigger_event.replace('_', ' ') }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">
                                {{ workflow.steps_count }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="workflow.is_active ? 'completed' : 'cancelled'"
                                    :label="workflow.is_active ? 'Active' : 'Inactive'"
                                />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                <Link
                                    :href="route('workflows.edit', workflow.id)"
                                    class="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </Link>
                                <button class="ml-3 text-red-600 hover:underline" @click="destroy(workflow)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!workflows.length">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No workflows yet — create deadline chains for filings, office actions, renewals and more.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
