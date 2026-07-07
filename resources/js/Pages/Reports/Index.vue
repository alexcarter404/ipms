<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import DateInput from '@/Components/DateInput.vue';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    types: Array,
    clients: Array,
    users: Array,
    saved: Array,
    running: Object,
    results: { type: Object, default: null },
});

const builder = reactive({
    type: props.running?.type ?? 'matters',
    client_id: props.running?.filters?.client_id ?? '',
    user_id: props.running?.filters?.user_id ?? '',
    status: props.running?.filters?.status ?? '',
    from: props.running?.filters?.from ?? '',
    to: props.running?.filters?.to ?? '',
});

const filters = () =>
    Object.fromEntries(
        Object.entries({
            client_id: builder.client_id,
            user_id: builder.user_id,
            status: builder.status,
            from: builder.from,
            to: builder.to,
        }).filter(([, v]) => v !== '' && v !== null)
    );

const run = () =>
    router.get(route('reports.index'), { type: builder.type, filters: filters() }, { preserveState: true, preserveScroll: true });

const csvUrl = () =>
    route('reports.csv', { type: builder.type, filters: filters() });

// Save the current definition
const showSave = ref(false);
const saveForm = useForm({ name: '', schedule: '' });

const save = () =>
    saveForm
        .transform((data) => ({
            name: data.name,
            schedule: data.schedule || null,
            type: builder.type,
            filters: filters(),
        }))
        .post(route('reports.store'), {
            preserveScroll: true,
            onSuccess: () => {
                saveForm.reset();
                showSave.value = false;
            },
        });

const load = (report) => {
    builder.type = report.type;
    builder.client_id = report.filters.client_id ?? '';
    builder.user_id = report.filters.user_id ?? '';
    builder.status = report.filters.status ?? '';
    builder.from = report.filters.from ?? '';
    builder.to = report.filters.to ?? '';
    run();
};

const confirmDelete = useDeleteConfirm();

const destroy = (report) =>
    confirmDelete(`Delete report “${report.name}”?`, () =>
        router.delete(route('reports.destroy', report.id), { preserveScroll: true })
    );
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Reports</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Build a report, run it, export it as CSV — or save it with a schedule and
                    receive it by email.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Builder -->
            <div class="rounded-lg bg-white p-5 shadow-sm" data-testid="report-builder">
                <div class="grid gap-3 lg:grid-cols-6">
                    <div>
                        <InputLabel value="Dataset" />
                        <SelectInput v-model="builder.type" :options="types" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Client" />
                        <SelectInput
                            v-model="builder.client_id"
                            :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                            placeholder="All"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Attorney / user" />
                        <SelectInput
                            v-model="builder.user_id"
                            :options="users.map((u) => ({ value: u.id, label: u.name }))"
                            placeholder="All"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Status" />
                        <TextInput v-model="builder.status" class="mt-1 w-full" placeholder="Any" />
                    </div>
                    <div>
                        <InputLabel value="From" />
                        <DateInput v-model="builder.from" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="To" />
                        <DateInput v-model="builder.to" class="mt-1 w-full" />
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <PrimaryButton @click="run">Run Report</PrimaryButton>
                    <a
                        v-if="results"
                        :href="csvUrl()"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Download CSV
                    </a>
                    <SecondaryButton v-if="results" @click="showSave = true">Save Report…</SecondaryButton>
                </div>
            </div>

            <!-- Results -->
            <div v-if="results" class="overflow-x-auto rounded-lg bg-white shadow-sm" data-testid="report-results">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th v-for="header in results.headers" :key="header" class="px-3 py-2.5 whitespace-nowrap">
                                {{ header }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(row, i) in results.rows" :key="i">
                            <td v-for="(cell, j) in row" :key="j" class="max-w-xs truncate px-3 py-2 text-gray-700">
                                {{ cell ?? '—' }}
                            </td>
                        </tr>
                        <tr v-if="!results.rows.length">
                            <td :colspan="results.headers.length" class="px-3 py-8 text-center text-gray-500">
                                No rows matched.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="px-3 py-2 text-xs text-gray-400">{{ results.rows.length }} row(s), capped at 500.</p>
            </div>

            <!-- Saved reports -->
            <div class="rounded-lg bg-white p-5 shadow-sm" data-testid="saved-reports">
                <h3 class="mb-3 font-semibold text-gray-800">Saved reports</h3>
                <ul class="divide-y text-sm">
                    <li v-for="report in saved" :key="report.id" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <div>
                            <button type="button" class="font-medium text-indigo-600 hover:underline" @click="load(report)">
                                {{ report.name }}
                            </button>
                            <span class="ml-2 text-gray-500">{{ report.type_label }}</span>
                            <Tag v-if="report.schedule" :value="report.schedule" severity="info" class="ml-2 !text-xs" />
                            <span class="ml-2 text-xs text-gray-400">
                                by {{ report.creator }}
                                {{ report.last_run_at ? `· last sent ${report.last_run_at}` : '' }}
                            </span>
                        </div>
                        <button type="button" class="text-xs text-red-600 hover:underline" @click="destroy(report)">
                            Delete
                        </button>
                    </li>
                    <li v-if="!saved.length" class="py-6 text-center text-gray-500">
                        Nothing saved yet — run a report and save it.
                    </li>
                </ul>
            </div>
        </div>

        <Dialog v-model:visible="showSave" modal header="Save Report" :style="{ width: '26rem' }">
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <InputLabel value="Name *" />
                    <TextInput v-model="saveForm.name" class="mt-1 w-full" />
                    <InputError :message="saveForm.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Email schedule" />
                    <SelectInput
                        v-model="saveForm.schedule"
                        :options="[
                            { value: 'daily', label: 'Daily (weekday mornings)' },
                            { value: 'weekly', label: 'Weekly (Mondays)' },
                        ]"
                        placeholder="No schedule — run manually"
                        class="mt-1 w-full"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showSave = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="saveForm.processing || !saveForm.name">Save</PrimaryButton>
                </div>
            </form>
        </Dialog>
    </AuthenticatedLayout>
</template>
