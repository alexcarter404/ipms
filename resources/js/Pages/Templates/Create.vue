<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TemplateForm from './Partials/TemplateForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    types: Array,
    officeEvents: Array,
    mergeFields: Array,
});

const form = useForm({
    name: '',
    channel: 'email',
    matter_type: '',
    subject: '',
    body: '',
    is_active: true,
    auto_event: '',
});

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            matter_type: d.matter_type || null,
            subject: d.subject || null,
            auto_event: d.auto_event || null,
        }))
        .post(route('templates.store'));
</script>

<template>
    <Head title="New Template" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Template</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <TemplateForm
                :office-events="officeEvents"
                :form="form"
                :types="types"
                :merge-fields="mergeFields"
                submit-label="Create Template"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
