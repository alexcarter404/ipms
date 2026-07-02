<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    client: Object,
    countries: Array,
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

const remove = (entity) => {
    if (!confirm(`Delete entity “${entity.name}”?`)) return;
    router.delete(route('entities.destroy', entity.id), { preserveScroll: true });
};
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
                    </div>
                    <div class="flex shrink-0 gap-2 text-xs">
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
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="form.is_default" type="checkbox" class="rounded text-indigo-600" />
                Make default entity
            </label>
            <div class="flex items-end gap-2 sm:col-span-2">
                <PrimaryButton :disabled="form.processing">Add Entity</PrimaryButton>
                <SecondaryButton type="button" @click="editing = null">Cancel</SecondaryButton>
            </div>
        </form>
    </div>
</template>
