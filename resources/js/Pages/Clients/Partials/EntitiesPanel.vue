<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    client: Object,
    countries: Array,
    billingCurrencies: { type: Array, default: () => [] },
    taxRates: { type: Array, default: () => [] },
    agreementTypes: { type: Array, default: () => [] },
});

const agreementLabel = (entity) => {
    if (!entity.billing_agreement) return null;
    return props.agreementTypes.find((t) => t.value === entity.billing_agreement.type)?.label
        ?? entity.billing_agreement.type;
};

// --- entity-level default fee agreement ---
const agreementFor = ref(null); // entity being edited, or null

const agreementForm = useForm({
    type: 'hourly',
    currency_code: '',
    increment_minutes: 6,
    blended_rate: '',
    cap_amount: '',
    fixed_amount: '',
    default_markup_pct: 0,
    requires_task_codes: false,
    notes: '',
});

const openAgreement = (entity) => {
    const a = entity.billing_agreement;
    agreementForm.defaults({
        type: a?.type ?? 'hourly',
        currency_code: a?.currency_code ?? '',
        increment_minutes: a?.increment_minutes ?? 6,
        blended_rate: a?.blended_rate ?? '',
        cap_amount: a?.cap_amount ?? '',
        fixed_amount: a?.fixed_amount ?? '',
        default_markup_pct: a?.default_markup_pct ?? 0,
        requires_task_codes: a?.requires_task_codes ?? false,
        notes: a?.notes ?? '',
    });
    agreementForm.reset();
    agreementForm.clearErrors();
    agreementFor.value = entity;
};

const saveAgreement = () =>
    agreementForm
        .transform((d) => ({
            ...d,
            currency_code: d.currency_code || null,
            blended_rate: d.blended_rate || null,
            cap_amount: d.cap_amount || null,
            fixed_amount: d.fixed_amount || null,
            default_markup_pct: d.default_markup_pct || 0,
        }))
        .post(route('entities.agreement.save', agreementFor.value.id), {
            preserveScroll: true,
            onSuccess: () => (agreementFor.value = null),
        });

const editing = ref(null); // null = closed, 'new' = create, id = edit

const blank = {
    name: '',
    registration_no: '',
    vat_number: '',
    country_code: '',
    address: '',
    billing_contact_name: '',
    billing_email: '',
    billing_address: '',
    billing_reference: '',
    currency_code: 'GBP',
    tax_rate_id: '',
    is_default: false,
    notes: '',
};

const form = useForm({ ...blank });

const openNew = () => {
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
    editing.value = 'new';
};

const openEdit = (entity) => {
    form.defaults({
        name: entity.name,
        registration_no: entity.registration_no ?? '',
        vat_number: entity.vat_number ?? '',
        country_code: entity.country_code ?? '',
        address: entity.address ?? '',
        billing_contact_name: entity.billing_contact_name ?? '',
        billing_email: entity.billing_email ?? '',
        billing_address: entity.billing_address ?? '',
        billing_reference: entity.billing_reference ?? '',
        currency_code: entity.currency_code ?? 'GBP',
        tax_rate_id: entity.tax_rate_id ?? '',
        is_default: entity.is_default,
        notes: entity.notes ?? '',
    });
    form.reset();
    form.clearErrors();
    editing.value = entity.id;
};

const submit = () => {
    const transform = (d) =>
        Object.fromEntries(
            Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])
        );

    const options = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };

    if (editing.value === 'new') {
        form.transform(transform).post(route('clients.entities.store', props.client.id), options);
    } else {
        form.transform(transform).patch(route('entities.update', editing.value), options);
    }
};

const confirmDelete = useDeleteConfirm();

const remove = (entity) =>
    confirmDelete(`Delete entity “${entity.name}”?`, () =>
        router.delete(route('entities.destroy', entity.id), { preserveScroll: true }));
</script>

<template>
    <div class="rounded-lg bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-800">Entities</h3>
                <p class="text-xs text-gray-500">
                    Legal entities in this client group — matters are billed to
                    their entity, or to the default.
                </p>
            </div>
            <button
                type="button"
                class="text-sm text-indigo-600 hover:underline"
                @click="editing === 'new' ? (editing = null) : openNew()"
            >
                {{ editing === 'new' ? 'Close' : 'Add entity' }}
            </button>
        </div>

        <ul class="divide-y">
            <li v-for="entity in client.entities" :key="entity.id" class="py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 text-sm">
                        <div class="font-medium text-gray-800">
                            {{ entity.name }}
                            <span
                                v-if="entity.is_default"
                                class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700"
                                >Default</span
                            >
                            <span v-if="entity.country_code" class="ml-1 text-xs text-gray-500">
                                {{ entity.country_code }}
                            </span>
                            <span v-if="entity.currency_code" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                                {{ entity.currency_code }}
                            </span>
                        </div>
                        <div class="mt-0.5 text-xs text-gray-500">
                            <template v-if="entity.registration_no">Reg. {{ entity.registration_no }} · </template>
                            <template v-if="entity.vat_number">VAT {{ entity.vat_number }} · </template>
                            {{ entity.matters_count }} matter(s)
                        </div>
                        <div v-if="entity.billing_email || entity.billing_reference" class="mt-0.5 text-xs text-gray-500">
                            Billing:
                            <template v-if="entity.billing_contact_name">{{ entity.billing_contact_name }} · </template>
                            <template v-if="entity.billing_email">{{ entity.billing_email }}</template>
                            <template v-if="entity.billing_reference"> · ref {{ entity.billing_reference }}</template>
                        </div>
                        <div class="mt-0.5 text-xs text-gray-500">
                            Fee agreement:
                            <span v-if="agreementLabel(entity)" class="font-medium text-gray-700">
                                {{ agreementLabel(entity) }}
                            </span>
                            <span v-else>— (hourly default)</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2 text-xs">
                        <button
                            type="button"
                            class="text-indigo-600 hover:underline"
                            @click="openAgreement(entity)"
                        >
                            Fee agreement
                        </button>
                        <button
                            type="button"
                            class="text-indigo-600 hover:underline"
                            @click="editing === entity.id ? (editing = null) : openEdit(entity)"
                        >
                            {{ editing === entity.id ? 'Close' : 'Edit' }}
                        </button>
                        <button
                            v-if="!entity.is_default"
                            type="button"
                            class="text-red-600 hover:underline"
                            @click="remove(entity)"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <!-- Inline edit form -->
                <form
                    v-if="editing === entity.id"
                    class="mt-3 grid gap-3 rounded-md bg-gray-50 p-4 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div>
                        <InputLabel value="Entity name *" />
                        <TextInput v-model="form.name" class="mt-1 w-full" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Company no" />
                            <TextInput v-model="form.registration_no" class="mt-1 w-full" />
                        </div>
                        <div>
                            <InputLabel value="VAT" />
                            <TextInput v-model="form.vat_number" class="mt-1 w-full" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Country" />
                        <SelectInput v-model="form.country_code" :options="countries" placeholder="—" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Registered address" />
                        <TextareaInput v-model="form.address" class="mt-1" rows="2" />
                    </div>
                    <div>
                        <InputLabel value="Billing contact" />
                        <TextInput v-model="form.billing_contact_name" class="mt-1 w-full" />
                    </div>
                    <div>
                        <InputLabel value="Billing email" />
                        <TextInput v-model="form.billing_email" type="email" class="mt-1 w-full" />
                        <InputError :message="form.errors.billing_email" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Billing address (if different)" />
                        <TextareaInput v-model="form.billing_address" class="mt-1" rows="2" />
                    </div>
                    <div>
                        <InputLabel value="Billing reference / PO" />
                        <TextInput v-model="form.billing_reference" class="mt-1 w-full" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Billing currency" />
                            <SelectInput v-model="form.currency_code" :options="billingCurrencies" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Tax treatment" />
                            <SelectInput v-model="form.tax_rate_id" :options="taxRates" placeholder="No tax" class="mt-1" />
                        </div>
                    </div>
                    <label v-if="!entity.is_default" class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.is_default" type="checkbox" class="rounded text-indigo-600" />
                        Make default entity
                    </label>
                    <div class="flex items-end gap-2 sm:col-span-2">
                        <PrimaryButton :disabled="form.processing">Save Entity</PrimaryButton>
                        <SecondaryButton type="button" @click="editing = null">Cancel</SecondaryButton>
                    </div>
                </form>
            </li>
        </ul>

        <!-- New entity form -->
        <form
            v-if="editing === 'new'"
            class="mt-3 grid gap-3 rounded-md bg-gray-50 p-4 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <div>
                <InputLabel value="Entity name *" />
                <TextInput v-model="form.name" class="mt-1 w-full" />
                <InputError :message="form.errors.name" class="mt-1" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <InputLabel value="Company no" />
                    <TextInput v-model="form.registration_no" class="mt-1 w-full" />
                </div>
                <div>
                    <InputLabel value="VAT" />
                    <TextInput v-model="form.vat_number" class="mt-1 w-full" />
                </div>
            </div>
            <div>
                <InputLabel value="Country" />
                <SelectInput v-model="form.country_code" :options="countries" placeholder="—" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Registered address" />
                <TextareaInput v-model="form.address" class="mt-1" rows="2" />
            </div>
            <div>
                <InputLabel value="Billing contact" />
                <TextInput v-model="form.billing_contact_name" class="mt-1 w-full" />
            </div>
            <div>
                <InputLabel value="Billing email" />
                <TextInput v-model="form.billing_email" type="email" class="mt-1 w-full" />
                <InputError :message="form.errors.billing_email" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Billing address (if different)" />
                <TextareaInput v-model="form.billing_address" class="mt-1" rows="2" />
            </div>
            <div>
                <InputLabel value="Billing reference / PO" />
                <TextInput v-model="form.billing_reference" class="mt-1 w-full" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <InputLabel value="Billing currency" />
                    <SelectInput v-model="form.currency_code" :options="billingCurrencies" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Tax treatment" />
                    <SelectInput v-model="form.tax_rate_id" :options="taxRates" placeholder="No tax" class="mt-1" />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="form.is_default" type="checkbox" class="rounded text-indigo-600" />
                Make default entity
            </label>
            <div class="flex items-end gap-2 sm:col-span-2">
                <PrimaryButton :disabled="form.processing">Add Entity</PrimaryButton>
                <SecondaryButton type="button" @click="editing = null">Cancel</SecondaryButton>
            </div>
        </form>

        <!-- Entity default fee agreement modal -->
        <Modal :show="agreementFor !== null" max-width="2xl" @close="agreementFor = null">
            <div v-if="agreementFor" class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">
                    Default Fee Agreement — {{ agreementFor.name }}
                </h3>
                <p class="text-sm text-gray-600">
                    Applies to every matter billed to this entity unless the
                    matter sets its own override.
                </p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel value="Arrangement" />
                        <SelectInput v-model="agreementForm.type" :options="agreementTypes" class="mt-1" />
                        <InputError :message="agreementForm.errors.type" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Currency" />
                        <SelectInput
                            v-model="agreementForm.currency_code"
                            :options="billingCurrencies"
                            placeholder="Entity default"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Increment (min)" />
                        <TextInput v-model="agreementForm.increment_minutes" type="number" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.increment_minutes" class="mt-1" />
                    </div>
                    <div v-if="agreementForm.type === 'blended'">
                        <InputLabel value="Blended rate /h" />
                        <TextInput v-model="agreementForm.blended_rate" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.blended_rate" class="mt-1" />
                    </div>
                    <div v-if="agreementForm.type === 'capped'">
                        <InputLabel value="Fee cap (per matter)" />
                        <TextInput v-model="agreementForm.cap_amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.cap_amount" class="mt-1" />
                    </div>
                    <div v-if="agreementForm.type === 'fixed'">
                        <InputLabel value="Fixed fee" />
                        <TextInput v-model="agreementForm.fixed_amount" type="number" step="0.01" class="mt-1 w-full" />
                        <InputError :message="agreementForm.errors.fixed_amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Markup %" />
                        <TextInput v-model="agreementForm.default_markup_pct" type="number" step="0.01" class="mt-1 w-full" />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="agreementForm.requires_task_codes" type="checkbox" class="rounded text-indigo-600" />
                    Task-based billing — require a task code on every time entry
                </label>
                <div>
                    <InputLabel value="Notes" />
                    <TextareaInput v-model="agreementForm.notes" class="mt-1" rows="2" />
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="agreementFor = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="agreementForm.processing" @click="saveAgreement">
                        Save Agreement
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
