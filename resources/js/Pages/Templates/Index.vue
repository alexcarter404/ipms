<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    templates: Array,
});

const destroy = (template) => {
    if (!confirm(`Delete template “${template.name}”?`)) return;
    router.delete(route('templates.destroy', template.id));
};
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
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Channel</th>
                            <th class="px-4 py-3">Matter type</th>
                            <th class="px-4 py-3 text-right">Used</th>
                            <th class="px-4 py-3">Active</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="template in templates" :key="template.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('templates.edit', template.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ template.name }}
                                </Link>
                                <div v-if="template.subject" class="max-w-md truncate text-xs text-gray-500">
                                    {{ template.subject }}
                                </div>
                            </td>
                            <td class="px-4 py-3 capitalize text-gray-600">{{ template.channel }}</td>
                            <td class="px-4 py-3 capitalize text-gray-600">
                                {{ template.matter_type ?? 'Any' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-800">
                                {{ template.communications_count }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="template.is_active ? 'completed' : 'cancelled'"
                                    :label="template.is_active ? 'Active' : 'Inactive'"
                                />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                <Link
                                    :href="route('templates.edit', template.id)"
                                    class="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </Link>
                                <button class="ml-3 text-red-600 hover:underline" @click="destroy(template)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!templates.length">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No templates yet — create reusable letters and emails with merge fields.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
