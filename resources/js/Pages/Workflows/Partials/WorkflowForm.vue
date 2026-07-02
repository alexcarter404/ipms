<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    form: Object,
    types: Array,
    triggerEvents: Array,
    submitLabel: { type: String, default: 'Save' },
});

const emit = defineEmits(['submit']);

const addStep = () =>
    props.form.steps.push({
        id: null,
        title: '',
        description: '',
        offset_value: 0,
        offset_unit: 'months',
        is_critical: false,
    });

const removeStep = (index) => props.form.steps.splice(index, 1);

const move = (index, delta) => {
    const target = index + delta;
    if (target < 0 || target >= props.form.steps.length) return;
    const steps = props.form.steps;
    [steps[index], steps[target]] = [steps[target], steps[index]];
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <InputLabel value="Name *" />
                    <TextInput v-model="form.name" class="mt-1 w-full" placeholder="e.g. PCT National Phase Entry" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Matter type" />
                    <SelectInput v-model="form.matter_type" :options="types" placeholder="Any type" class="mt-1" />
                    <InputError :message="form.errors.matter_type" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Trigger event *" />
                    <SelectInput v-model="form.trigger_event" :options="triggerEvents" class="mt-1" />
                    <InputError :message="form.errors.trigger_event" class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500">
                        Step due dates are calculated relative to this date.
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <InputLabel value="Description" />
                    <TextareaInput v-model="form.description" class="mt-1" rows="2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                    Active (available to apply to matters)
                </label>
            </div>
        </section>

        <!-- Steps -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Steps</h3>
                <SecondaryButton type="button" @click="addStep">Add Step</SecondaryButton>
            </div>
            <InputError :message="form.errors.steps" class="mb-2" />

            <div
                v-for="(step, i) in form.steps"
                :key="i"
                class="mb-3 rounded-md border border-gray-200 p-4"
            >
                <div class="grid gap-3 sm:grid-cols-12">
                    <div class="sm:col-span-5">
                        <InputLabel value="Title *" />
                        <TextInput v-model="step.title" class="mt-1 w-full" />
                        <InputError :message="form.errors[`steps.${i}.title`]" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Offset *" />
                        <TextInput v-model.number="step.offset_value" type="number" class="mt-1 w-full" />
                        <InputError :message="form.errors[`steps.${i}.offset_value`]" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Unit" />
                        <SelectInput
                            v-model="step.offset_unit"
                            :options="[
                                { value: 'days', label: 'Days' },
                                { value: 'weeks', label: 'Weeks' },
                                { value: 'months', label: 'Months' },
                                { value: 'years', label: 'Years' },
                            ]"
                            class="mt-1"
                        />
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-3">
                        <label class="flex items-center gap-1.5 pb-2 text-sm text-gray-600">
                            <input v-model="step.is_critical" type="checkbox" class="rounded text-indigo-600" />
                            Critical
                        </label>
                        <div class="ml-auto flex gap-1 pb-1 text-xs">
                            <button type="button" class="rounded border px-2 py-1 hover:bg-gray-50" title="Move up" @click="move(i, -1)">↑</button>
                            <button type="button" class="rounded border px-2 py-1 hover:bg-gray-50" title="Move down" @click="move(i, 1)">↓</button>
                            <button type="button" class="rounded border border-red-200 px-2 py-1 text-red-600 hover:bg-red-50" @click="removeStep(i)">✕</button>
                        </div>
                    </div>
                    <div class="sm:col-span-12">
                        <InputLabel value="Description" />
                        <TextInput v-model="step.description" class="mt-1 w-full" />
                    </div>
                </div>
            </div>

            <p v-if="!form.steps.length" class="py-4 text-center text-sm text-gray-500">
                No steps yet — add the deadline chain this workflow should create.
            </p>
        </section>

        <div class="flex items-center gap-3">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
            <Link :href="route('workflows.index')" class="text-sm text-gray-600 hover:underline">
                Cancel
            </Link>
        </div>
    </form>
</template>
