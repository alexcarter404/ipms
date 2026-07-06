<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WorkflowForm from './Partials/WorkflowForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    workflow: Object,
    types: Array,
    triggerEvents: Array,
    contractFields: Array,
    officeEvents: Array,
});

const form = useForm({
    name: props.workflow.name,
    matter_type: props.workflow.matter_type ?? '',
    trigger_event: props.workflow.trigger_event,
    description: props.workflow.description ?? '',
    is_active: props.workflow.is_active,
    steps: props.workflow.steps.map((s) => ({
        id: s.id,
        title: s.title,
        description: s.description ?? '',
        offset_value: s.offset_value,
        offset_unit: s.offset_unit,
        is_critical: s.is_critical,
        required_fields: s.required_fields ?? [],
        completed_by_event: s.completed_by_event ?? '',
    })),
});

const submit = () =>
    form
        .transform((d) => ({ ...d, matter_type: d.matter_type || null }))
        .patch(route('workflows.update', props.workflow.id));
</script>

<template>
    <Head :title="`Edit ${workflow.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Workflow — {{ workflow.name }}
            </h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <WorkflowForm
                :form="form"
                :types="types"
                :trigger-events="triggerEvents"
                :contract-fields="contractFields"
                :office-events="officeEvents"
                submit-label="Save Changes"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
