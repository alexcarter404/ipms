<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TemplateForm from './Partials/TemplateForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    template: Object,
    types: Array,
    officeEvents: Array,
    mergeFields: Array,
});

const form = useForm({
    name: props.template.name,
    channel: props.template.channel,
    matter_type: props.template.matter_type ?? '',
    subject: props.template.subject ?? '',
    body: props.template.body,
    is_active: props.template.is_active,
    auto_event: props.template.auto_event ?? '',
});

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            matter_type: d.matter_type || null,
            subject: d.subject || null,
            auto_event: d.auto_event || null,
        }))
        .patch(route('templates.update', props.template.id));
</script>

<template>
    <Head :title="`Edit ${template.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Template — {{ template.name }}
            </h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <TemplateForm
                :office-events="officeEvents"
                :form="form"
                :types="types"
                :merge-fields="mergeFields"
                submit-label="Save Changes"
                @submit="submit"
            />
        </div>
    </AuthenticatedLayout>
</template>
