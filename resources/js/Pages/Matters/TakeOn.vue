<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import MatterForm from './Partials/MatterForm.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    options: Object,
    workflows: Array,
    contractFields: Array,
    triggerDateFields: Object,
});

const form = useForm({
    workflow_id: '',
    entry_step_id: '',
    base_date: '',
    reference: '',
    matter_type: 'patent',
    title: '',
    client_id: '',
    client_entity_id: '',
    family_id: '',
    parent_id: '',
    responsible_user_id: '',
    country_code: '',
    filing_route: '',
    status: 'filed',
    application_no: '',
    application_date: '',
    publication_no: '',
    publication_date: '',
    registration_no: '',
    registration_date: '',
    priority_no: '',
    priority_date: '',
    expiry_date: '',
    description: '',
    notes: '',
});

const selectedWorkflow = computed(() =>
    props.workflows.find((w) => w.id === Number(form.workflow_id))
);

const entrySteps = computed(() =>
    (selectedWorkflow.value?.steps ?? []).map((step, i) => ({
        value: step.id,
        label: `${i + 1}. ${step.title}`,
    }))
);

const entryStep = computed(() =>
    selectedWorkflow.value?.steps.find((s) => s.id === Number(form.entry_step_id))
);

// The stage contract: union of required fields for every step up to and
// including the entry stage — being at a stage presumes earlier data.
const contract = computed(() => {
    if (!selectedWorkflow.value || !entryStep.value) return [];
    const fields = new Set();
    for (const step of selectedWorkflow.value.steps) {
        if (step.sort_order <= entryStep.value.sort_order) {
            (step.required_fields ?? []).forEach((f) => fields.add(f));
        }
    }
    return [...fields];
});

// The trigger's base date: a matter field when the trigger implies one,
// otherwise an explicit base date input.
const triggerDateField = computed(() =>
    selectedWorkflow.value
        ? props.triggerDateFields[selectedWorkflow.value.trigger_event] ?? null
        : null
);

const fieldLabel = (key) =>
    props.contractFields.find((f) => f.value === key)?.label ?? key;

const isFilled = (key) => Boolean(form[key]);

const remainingSteps = computed(() => {
    if (!selectedWorkflow.value || !entryStep.value) return [];
    return selectedWorkflow.value.steps.filter(
        (s) => s.sort_order >= entryStep.value.sort_order
    );
});

watch(
    () => form.workflow_id,
    () => (form.entry_step_id = '')
);

const submit = () =>
    form
        .transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])
            )
        )
        .post(route('matters.take-on.store'));
</script>

<template>
    <Head title="Matter Take-On" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Matter Take-On
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Open a matter part-way through a workflow — earlier stages
                    are treated as done, and each stage's data contract tells
                    you exactly what must be captured.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Stage selection + contract -->
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-4 font-semibold text-gray-800">Workflow &amp; Entry Stage</h3>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel value="Workflow *" />
                        <SelectInput
                            v-model="form.workflow_id"
                            :options="workflows.map((w) => ({ value: w.id, label: w.name }))"
                            placeholder="Select…"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.workflow_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Enter at stage *" />
                        <SelectInput
                            v-model="form.entry_step_id"
                            :options="entrySteps"
                            placeholder="Select…"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.entry_step_id" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-500">
                            The first step still to be actioned — earlier steps
                            are considered complete.
                        </p>
                    </div>
                    <div v-if="selectedWorkflow && !triggerDateField">
                        <InputLabel value="Base date *" />
                        <DateInput v-model="form.base_date" class="mt-1" />
                        <InputError :message="form.errors.base_date" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-500">
                            This workflow's trigger has no matter date — anchor
                            the remaining deadlines explicitly.
                        </p>
                    </div>
                </div>

                <!-- Contract checklist -->
                <div v-if="entryStep" class="mt-5 rounded-md bg-indigo-50 p-4" data-testid="stage-contract">
                    <h4 class="text-sm font-semibold text-indigo-900">
                        Data contract for entering at “{{ entryStep.title }}”
                    </h4>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li
                            v-for="key in contract"
                            :key="key"
                            class="flex items-center gap-2"
                            :class="isFilled(key) ? 'text-green-700' : 'text-indigo-900'"
                        >
                            <span>{{ isFilled(key) ? '✓' : '○' }}</span>
                            {{ fieldLabel(key) }}
                        </li>
                        <li
                            v-if="triggerDateField && !contract.includes(triggerDateField)"
                            class="flex items-center gap-2"
                            :class="isFilled(triggerDateField) ? 'text-green-700' : 'text-indigo-900'"
                        >
                            <span>{{ isFilled(triggerDateField) ? '✓' : '○' }}</span>
                            {{ fieldLabel(triggerDateField) }}
                            <span class="text-xs">(anchors the remaining deadlines)</span>
                        </li>
                        <li v-if="!contract.length && !triggerDateField" class="text-indigo-900">
                            This stage has no data contract — only the standard matter fields apply.
                        </li>
                    </ul>
                    <p v-if="remainingSteps.length" class="mt-3 text-xs text-indigo-800">
                        {{ remainingSteps.length }} task(s) will be created, starting with
                        “{{ remainingSteps[0].title }}”.
                    </p>
                </div>
            </section>

            <MatterForm
                :form="form"
                :options="options"
                submit-label="Take On Matter"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
