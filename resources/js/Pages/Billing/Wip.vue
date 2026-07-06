<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    groups: Array,
    filters: Object,
    clients: Array,
    users: Array,
});

const form = reactive({
    client_id: props.filters.client_id ?? '',
    user_id: props.filters.user_id ?? '',
});

const pruned = () =>
    Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));

let timeout = null;
watch(form, () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(route('billing.wip'), pruned(), { preserveState: true, replace: true });
    }, 300);
});

onUnmounted(() => clearTimeout(timeout));

const money = (amount, currency) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount ?? 0);

// --- selection: entity id -> Set of matter ids ---
const selected = ref({});

const isSelected = (entityId, matterId) => selected.value[entityId]?.has(matterId) ?? false;

const toggle = (entityId, matterId) => {
    const set = new Set(selected.value[entityId] ?? []);
    set.has(matterId) ? set.delete(matterId) : set.add(matterId);
    selected.value = { ...selected.value, [entityId]: set };
};

const selectionCount = (entityId) => selected.value[entityId]?.size ?? 0;

const draftInvoice = (group) => {
    const ids = [...(selected.value[group.entity.id] ?? [])];
    router.post(
        route('entities.invoices.store', group.entity.id),
        ids.length ? { matter_ids: ids } : {},
    );
};
</script>

<template>
    <Head title="Work in Progress" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Work in Progress
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Unbilled time, disbursements and charges across the firm,
                        grouped by the entity that gets the bill.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('invoices.index')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Invoices
                    </Link>
                    <Link
                        :href="route('billing.settings')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50"
                    >
                        Billing Settings
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <SelectInput
                    v-model="form.client_id"
                    :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                    placeholder="All clients"
                />
                <SelectInput
                    v-model="form.user_id"
                    :options="users.map((u) => ({ value: u.id, label: u.name }))"
                    placeholder="All attorneys"
                />
            </div>

            <!-- Entity groups -->
            <div
                v-for="group in groups"
                :key="group.entity.id"
                class="overflow-hidden rounded-lg bg-white shadow-sm"
                :data-testid="`wip-entity-${group.entity.id}`"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ group.entity.name }}</h3>
                        <p class="text-xs text-gray-500">
                            {{ group.entity.client_name }} · billed in {{ group.entity.currency }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-semibold text-gray-900">
                            {{ money(group.total, group.entity.currency) }}
                        </span>
                        <PrimaryButton :disabled="group.total <= 0" @click="draftInvoice(group)">
                            {{
                                selectionCount(group.entity.id)
                                    ? `Draft Invoice (${selectionCount(group.entity.id)} selected)`
                                    : 'Draft Invoice (all)'
                            }}
                        </PrimaryButton>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="w-8 px-4 py-2.5"></th>
                                <th class="px-4 py-2.5">Matter</th>
                                <th class="px-4 py-2.5">Fee arrangement</th>
                                <th class="px-4 py-2.5 text-right">Time</th>
                                <th class="px-4 py-2.5 text-right">Disbursements</th>
                                <th class="px-4 py-2.5 text-right">Charges</th>
                                <th class="px-4 py-2.5 text-right">Billable WIP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="matter in group.matters" :key="matter.id">
                                <td class="px-4 py-2.5">
                                    <input
                                        type="checkbox"
                                        class="rounded text-indigo-600"
                                        :checked="isSelected(group.entity.id, matter.id)"
                                        @change="toggle(group.entity.id, matter.id)"
                                    />
                                </td>
                                <td class="px-4 py-2.5">
                                    <Link
                                        :href="route('matters.show', matter.id)"
                                        class="whitespace-nowrap font-medium text-indigo-600 hover:underline"
                                    >
                                        {{ matter.reference }}
                                    </Link>
                                    <span class="ml-2 hidden max-w-xs truncate text-gray-500 lg:inline">
                                        {{ matter.title }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ matter.agreement }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-700">
                                    {{ money(matter.time, matter.currency) }}
                                    <span v-if="!matter.bills_time && matter.time > 0" class="text-xs text-gray-500" title="Time is tracked but not billed under this fee arrangement">
                                        (not billed)
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-700">{{ money(matter.disbursements, matter.currency) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-700">{{ money(matter.charges, matter.currency) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium text-gray-800">{{ money(matter.billable_total, matter.currency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="!groups.length" class="rounded-lg bg-white p-10 text-center text-gray-500 shadow-sm">
                No unbilled work in progress — log time, disbursements or charges
                on a matter's Billing tab and it will appear here.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
