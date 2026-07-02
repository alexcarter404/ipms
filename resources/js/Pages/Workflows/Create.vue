<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WorkflowForm from './Partials/WorkflowForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    types: Array,
    triggerEvents: Array,
});

const form = useForm({
    name: '',
    matter_type: '',
    trigger_event: 'filing',
    description: '',
    is_active: true,
    steps: [],
});

const submit = () =>
    form
        .transform((d) => ({ ...d, matter_type: d.matter_type || null }))
        .post(route('workflows.store'));
</script>

<template>
    <Head title="New Workflow" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Workflow</h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <WorkflowForm
                :form="form"
                :types="types"
                :trigger-events="triggerEvents"
                submit-label="Create Workflow"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
