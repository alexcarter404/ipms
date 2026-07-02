<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import DateInput from '@/Components/DateInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';

import { computed, watch } from 'vue';

const props = defineProps({
    form: Object, // Inertia useForm instance
    options: Object,
    submitLabel: { type: String, default: 'Save' },
});

const emit = defineEmits(['submit']);

// Entities belonging to the selected client; billing defaults to the
// client's default entity when none is chosen.
const clientEntities = computed(() => {
    const client = props.options.clients.find(
        (c) => c.id === Number(props.form.client_id)
    );
    return client?.entities ?? [];
});

watch(
    () => props.form.client_id,
    () => {
        if (
            props.form.client_entity_id &&
            !clientEntities.value.some((e) => e.id === Number(props.form.client_entity_id))
        ) {
            props.form.client_entity_id = '';
        }
    }
);
</script>

<template>
    <form class="space-y-8" @submit.prevent="emit('submit')">
        <!-- Identity -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Matter</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <InputLabel value="Reference *" />
                    <TextInput v-model="form.reference" class="mt-1 w-full" placeholder="e.g. P-2026-0001" />
                    <InputError :message="form.errors.reference" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Type *" />
                    <SelectInput v-model="form.matter_type" :options="options.types" class="mt-1" />
                    <InputError :message="form.errors.matter_type" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Status *" />
                    <SelectInput v-model="form.status" :options="options.statuses" class="mt-1" />
                    <InputError :message="form.errors.status" class="mt-1" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <InputLabel value="Title *" />
                    <TextInput v-model="form.title" class="mt-1 w-full" />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Jurisdiction *" />
                    <SelectInput v-model="form.country_code" :options="options.countries" placeholder="Select…" class="mt-1" />
                    <InputError :message="form.errors.country_code" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Filing route" />
                    <SelectInput v-model="form.filing_route" :options="options.filingRoutes" placeholder="—" class="mt-1" />
                    <InputError :message="form.errors.filing_route" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Responsible attorney" />
                    <SelectInput
                        v-model="form.responsible_user_id"
                        :options="options.users.map((u) => ({ value: u.id, label: u.name }))"
                        placeholder="—"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.responsible_user_id" class="mt-1" />
                </div>
            </div>
        </section>

        <!-- Ownership / relations -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Client &amp; Relations</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <InputLabel value="Client *" />
                    <SelectInput
                        v-model="form.client_id"
                        :options="options.clients.map((c) => ({ value: c.id, label: `${c.code ?? ''} ${c.name}`.trim() }))"
                        placeholder="Select…"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.client_id" class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500">
                        Missing a client?
                        <Link :href="route('clients.create')" class="text-indigo-600 hover:underline">Create one</Link>
                    </p>
                </div>
                <div>
                    <InputLabel value="Billing entity" />
                    <SelectInput
                        v-model="form.client_entity_id"
                        :options="clientEntities.map((e) => ({
                            value: e.id,
                            label: e.is_default ? `${e.name} (default)` : e.name,
                        }))"
                        placeholder="Client default"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.client_entity_id" class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500">
                        Which entity in the client group is billed for this matter.
                    </p>
                </div>
                <div>
                    <InputLabel value="Family" />
                    <SelectInput
                        v-model="form.family_id"
                        :options="options.families.map((f) => ({ value: f.id, label: `${f.reference} — ${f.name}` }))"
                        placeholder="—"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.family_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Parent matter" />
                    <SelectInput
                        v-model="form.parent_id"
                        :options="options.matters.map((m) => ({ value: m.id, label: `${m.reference} — ${m.title}` }))"
                        placeholder="—"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.parent_id" class="mt-1" />
                </div>
            </div>
        </section>

        <!-- Key numbers & dates -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Official Numbers &amp; Dates</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <InputLabel value="Application no" />
                    <TextInput v-model="form.application_no" class="mt-1 w-full" />
                    <InputError :message="form.errors.application_no" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Application date" />
                    <DateInput v-model="form.application_date" class="mt-1" />
                    <InputError :message="form.errors.application_date" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Publication no" />
                    <TextInput v-model="form.publication_no" class="mt-1 w-full" />
                    <InputError :message="form.errors.publication_no" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Publication date" />
                    <DateInput v-model="form.publication_date" class="mt-1" />
                    <InputError :message="form.errors.publication_date" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Registration / grant no" />
                    <TextInput v-model="form.registration_no" class="mt-1 w-full" />
                    <InputError :message="form.errors.registration_no" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Registration / grant date" />
                    <DateInput v-model="form.registration_date" class="mt-1" />
                    <InputError :message="form.errors.registration_date" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Priority no" />
                    <TextInput v-model="form.priority_no" class="mt-1 w-full" />
                    <InputError :message="form.errors.priority_no" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Priority date" />
                    <DateInput v-model="form.priority_date" class="mt-1" />
                    <InputError :message="form.errors.priority_date" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Expiry date" />
                    <DateInput v-model="form.expiry_date" class="mt-1" />
                    <InputError :message="form.errors.expiry_date" class="mt-1" />
                </div>
            </div>
        </section>

        <!-- Notes -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Description &amp; Notes</h3>
            <div class="space-y-4">
                <div>
                    <InputLabel value="Description" />
                    <TextareaInput v-model="form.description" class="mt-1" rows="3" />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Internal notes" />
                    <TextareaInput v-model="form.notes" class="mt-1" rows="3" />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
            <Link :href="route('matters.index')" class="text-sm text-gray-600 hover:underline">
                Cancel
            </Link>
        </div>
    </form>
</template>
