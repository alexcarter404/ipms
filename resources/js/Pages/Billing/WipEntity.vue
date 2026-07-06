<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    wip: Object,
    baseCurrency: String,
});

const money = (amount, currency) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount ?? 0);

const shortDate = (value) =>
    new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

const kindLabel = { time: 'Time', disbursement: 'Disb.', charge: 'Charge' };

// --- matter selection for the consolidated bill ---
const selected = ref(new Set());

const toggle = (matterId) => {
    const next = new Set(selected.value);
    next.has(matterId) ? next.delete(matterId) : next.add(matterId);
    selected.value = next;
};

const draftInvoice = () =>
    router.post(
        route('entities.invoices.store', props.wip.entity.id),
        selected.value.size ? { matter_ids: [...selected.value] } : {},
    );

const billMatter = (matter) =>
    router.post(route('entities.invoices.store', props.wip.entity.id), {
        matter_ids: [matter.id],
    });

// --- inline description editing ---
const editing = ref(null); // { kind, id, text } | null
const saving = ref(false);

const startEdit = (item) => {
    editing.value = { kind: item.kind, id: item.id, text: item.description };
};

const saveEdit = () => {
    const { kind, id, text } = editing.value;
    const target = {
        time: { name: 'time-entries.update', payload: { narrative: text } },
        disbursement: { name: 'disbursements.update', payload: { description: text } },
        charge: { name: 'charges.update', payload: { description: text } },
    }[kind];

    saving.value = true;
    router.patch(route(target.name, id), target.payload, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
        onFinish: () => (saving.value = false),
    });
};
</script>

<template>
    <Head :title="`WIP — ${wip.entity.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <Link :href="route('billing.wip')" class="hover:underline">Work in Progress</Link>
                        <span>/</span>
                    </div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ wip.entity.name }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        <Link :href="route('clients.show', wip.entity.client_id)" class="text-indigo-600 hover:underline">
                            {{ wip.entity.client_name }}
                        </Link>
                        · billed in {{ wip.entity.currency }}
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-right text-lg font-semibold text-gray-900">
                        {{ money(wip.total, wip.entity.currency) }}
                        <span v-if="wip.entity.currency !== baseCurrency" class="block text-xs font-normal text-gray-500">
                            ≈ {{ money(wip.total_base, baseCurrency) }} base
                        </span>
                    </span>
                    <PrimaryButton :disabled="wip.total <= 0" @click="draftInvoice">
                        {{
                            selected.size
                                ? `Draft Invoice (${selected.size} matter${selected.size > 1 ? 's' : ''})`
                                : 'Draft Invoice (all)'
                        }}
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <div
                v-for="matter in wip.matters"
                :key="matter.id"
                class="overflow-hidden rounded-lg bg-white shadow-sm"
                :data-testid="`wip-matter-${matter.reference}`"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            class="rounded text-indigo-600"
                            :checked="selected.has(matter.id)"
                            @change="toggle(matter.id)"
                        />
                        <span>
                            <Link :href="route('matters.show', matter.id)" class="font-semibold text-indigo-600 hover:underline">
                                {{ matter.reference }}
                            </Link>
                            <span class="ml-2 text-sm text-gray-500">{{ matter.title }}</span>
                        </span>
                    </label>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-gray-500">{{ matter.agreement }}</span>
                        <span class="font-semibold text-gray-900">{{ money(matter.billable_total, matter.currency) }}</span>
                        <button class="text-indigo-600 hover:underline" @click="billMatter(matter)">
                            Bill this matter
                        </button>
                    </div>
                </div>

                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in matter.items" :key="`${item.kind}-${item.id}`">
                            <td class="w-28 whitespace-nowrap px-4 py-2.5 text-gray-600">{{ shortDate(item.date) }}</td>
                            <td class="w-20 px-2 py-2.5">
                                <StatusBadge :status="item.kind" :label="kindLabel[item.kind]" />
                            </td>
                            <td class="px-4 py-2.5">
                                <template v-if="editing && editing.kind === item.kind && editing.id === item.id">
                                    <div class="flex items-center gap-2">
                                        <TextInput
                                            v-model="editing.text"
                                            class="w-full !py-1 text-sm"
                                            @keyup.enter="saveEdit"
                                            @keyup.esc="editing = null"
                                        />
                                        <PrimaryButton class="!px-2.5 !py-1 !text-xs" :disabled="saving" @click="saveEdit">
                                            Save
                                        </PrimaryButton>
                                        <SecondaryButton class="!px-2.5 !py-1 !text-xs" @click="editing = null">
                                            Cancel
                                        </SecondaryButton>
                                    </div>
                                </template>
                                <template v-else>
                                    <span class="text-gray-800">{{ item.description }}</span>
                                    <button
                                        class="ml-2 text-xs text-indigo-600 hover:underline"
                                        title="Amend the wording that will appear on the invoice"
                                        @click="startEdit(item)"
                                    >
                                        Amend
                                    </button>
                                    <div v-if="item.meta" class="text-xs text-gray-500">{{ item.meta }}</div>
                                </template>
                            </td>
                            <td class="w-36 whitespace-nowrap px-4 py-2.5 text-right font-medium text-gray-800">
                                {{ money(item.amount, item.currency) }}
                                <span
                                    v-if="!item.billed"
                                    class="block text-xs font-normal text-gray-500"
                                    title="Time is tracked but not billed under this fee arrangement"
                                >
                                    not billed
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!wip.matters.length" class="rounded-lg bg-white p-10 text-center text-gray-500 shadow-sm">
                Nothing left to bill for this entity.
                <Link :href="route('billing.wip')" class="text-indigo-600 hover:underline">Back to WIP</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
