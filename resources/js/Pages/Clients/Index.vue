<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    clients: Object,
    filters: Object,
});

const search = ref(props.filters.search ?? '');

let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('clients.index'),
            value ? { search: value } : {},
            { preserveState: true, replace: true }
        );
    }, 300);
});
</script>

<template>
    <Head title="Clients" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Clients</h2>
                <Link
                    :href="route('clients.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    New Client
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search name or code…"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-80"
                />
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Country</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3 text-right">Matters</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <Link
                                    :href="route('clients.show', client.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ client.code }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ client.name }}</td>
                            <td class="px-4 py-3 capitalize text-gray-600">{{ client.type }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ client.country_code ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ client.email ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">
                                {{ client.matters_count }}
                            </td>
                        </tr>
                        <tr v-if="!clients.data.length">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No clients found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="clients.links" />
        </div>
    </AuthenticatedLayout>
</template>
