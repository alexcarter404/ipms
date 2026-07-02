<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    rules: Array,
});

const destroy = (rule) => {
    if (!confirm(`Delete rule “${rule.name}”? Existing renewals are kept.`)) return;
    router.delete(route('renewal-rules.destroy', rule.id));
};
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
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Rule</th>
                            <th class="px-4 py-3">Matter type</th>
                            <th class="px-4 py-3">Jurisdiction</th>
                            <th class="px-4 py-3">Schedule</th>
                            <th class="px-4 py-3">Grace</th>
                            <th class="px-4 py-3">Active</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="rule in rules" :key="rule.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('renewal-rules.edit', rule.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ rule.name }}
                                </Link>
                                <div v-if="rule.notes" class="mt-0.5 max-w-md truncate text-xs text-gray-500">
                                    {{ rule.notes }}
                                </div>
                            </td>
                            <td class="px-4 py-3 capitalize text-gray-600">{{ rule.matter_type }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                <template v-if="rule.country_code">
                                    {{ rule.country_code }}
                                    <span class="text-xs text-gray-400"> — {{ rule.country_name }}</span>
                                </template>
                                <span v-else class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                    Any (default)
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ rule.summary }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ rule.grace_months }} mo</td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="rule.is_active ? 'completed' : 'cancelled'"
                                    :label="rule.is_active ? 'Active' : 'Inactive'"
                                />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                <Link
                                    :href="route('renewal-rules.edit', rule.id)"
                                    class="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </Link>
                                <button class="ml-3 text-red-600 hover:underline" @click="destroy(rule)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rules.length">
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No rules configured — the scheduler cannot generate renewals without them.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
