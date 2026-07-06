<script setup>
import DateInput from '@/Components/DateInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    form: Object,
    clients: Array,
    matters: Array,
    currencies: Array,
    taxRates: Array,
    submitLabel: { type: String, default: 'Save' },
});

const emit = defineEmits(['submit']);

const selectedClient = computed(() =>
    props.clients.find((c) => c.id === Number(props.form.client_id))
);

const entityOptions = computed(() =>
    (selectedClient.value?.entities ?? []).map((e) => ({
        value: e.id,
        label: e.name + (e.is_default ? ' (default)' : ''),
    }))
);

const addLine = () => props.form.lines.push({ description: '', quantity: 1, unit_amount: '' });
const removeLine = (index) => props.form.lines.splice(index, 1);

const lineTotal = (line) =>
    (Number(line.quantity) || 0) * (Number(line.unit_amount) || 0);

const subtotal = computed(() =>
    props.form.lines.reduce((sum, line) => sum + lineTotal(line), 0)
);

const taxPct = computed(() => {
    const selected = props.taxRates.find((t) => t.value === Number(props.form.tax_rate_id));
    if (!selected) return 0;
    const match = selected.label.match(/\(([\d.]+)%\)/);
    return match ? Number(match[1]) : 0;
});

const money = (amount) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.form.currency_code || 'GBP',
    }).format(amount ?? 0);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <InputLabel value="Client *" />
                    <SelectInput
                        v-model="form.client_id"
                        :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                        placeholder="Select…"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.client_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Entity" />
                    <SelectInput
                        v-model="form.client_entity_id"
                        :options="entityOptions"
                        placeholder="Client default"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.client_entity_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Matter" />
                    <SelectInput
                        v-model="form.matter_id"
                        :options="matters.map((m) => ({ value: m.id, label: `${m.reference} — ${m.title}` }))"
                        placeholder="—"
                        class="mt-1"
                    />
                </div>
                <div>
                    <InputLabel value="Currency *" />
                    <SelectInput v-model="form.currency_code" :options="currencies" class="mt-1" />
                    <InputError :message="form.errors.currency_code" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Tax treatment" />
                    <SelectInput v-model="form.tax_rate_id" :options="taxRates" placeholder="No tax" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Valid until" />
                    <DateInput v-model="form.valid_until" class="mt-1" />
                    <InputError :message="form.errors.valid_until" class="mt-1" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <InputLabel value="Notes" />
                    <TextareaInput v-model="form.notes" class="mt-1" rows="2" />
                </div>
            </div>
        </section>

        <!-- Lines -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Lines</h3>
                <SecondaryButton type="button" @click="addLine">Add Line</SecondaryButton>
            </div>
            <InputError :message="form.errors.lines" class="mb-2" />

            <div v-for="(line, i) in form.lines" :key="i" class="mb-3 flex items-start gap-3">
                <div class="flex-1">
                    <TextInput v-model="line.description" placeholder="Description" class="w-full" />
                    <InputError :message="form.errors[`lines.${i}.description`]" class="mt-1" />
                </div>
                <div class="w-24">
                    <TextInput v-model="line.quantity" type="number" step="0.01" placeholder="Qty" class="w-full" />
                    <InputError :message="form.errors[`lines.${i}.quantity`]" class="mt-1" />
                </div>
                <div class="w-36">
                    <TextInput v-model="line.unit_amount" type="number" step="0.01" placeholder="Unit amount" class="w-full" />
                    <InputError :message="form.errors[`lines.${i}.unit_amount`]" class="mt-1" />
                </div>
                <div class="w-28 pt-2 text-right text-sm font-medium text-gray-800">
                    {{ money(lineTotal(line)) }}
                </div>
                <button type="button" class="pt-2 text-red-600 hover:underline" @click="removeLine(i)">✕</button>
            </div>

            <p v-if="!form.lines.length" class="py-4 text-center text-sm text-gray-500">
                No lines yet — add the work being quoted.
            </p>

            <dl class="ml-auto mt-4 w-64 space-y-1 border-t border-gray-200 pt-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Subtotal</dt>
                    <dd class="font-medium text-gray-800">{{ money(subtotal) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Tax ({{ taxPct }}%)</dt>
                    <dd class="font-medium text-gray-800">{{ money(subtotal * taxPct / 100) }}</dd>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-1">
                    <dt class="font-semibold text-gray-700">Total</dt>
                    <dd class="font-semibold text-gray-900">{{ money(subtotal * (1 + taxPct / 100)) }}</dd>
                </div>
            </dl>
        </section>

        <div class="flex items-center gap-3">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
            <Link :href="route('quotes.index')" class="text-sm text-gray-600 hover:underline">Cancel</Link>
        </div>
    </form>
</template>
