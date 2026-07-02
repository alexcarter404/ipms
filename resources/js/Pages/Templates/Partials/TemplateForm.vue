<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    form: Object,
    types: Array,
    mergeFields: Array,
    submitLabel: { type: String, default: 'Save' },
});

const emit = defineEmits(['submit']);

const subjectPlaceholder = 'e.g. {{matter.reference}} — Filing Confirmation';

const asMergeTag = (field) => '{{' + field + '}}';
</script>

<template>
    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="emit('submit')">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <InputLabel value="Name *" />
                        <TextInput v-model="form.name" class="mt-1 w-full" placeholder="e.g. Filing Confirmation" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Channel *" />
                        <SelectInput
                            v-model="form.channel"
                            :options="[
                                { value: 'email', label: 'Email' },
                                { value: 'letter', label: 'Letter' },
                            ]"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.channel" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Matter type" />
                        <SelectInput v-model="form.matter_type" :options="types" placeholder="Any type" class="mt-1" />
                        <InputError :message="form.errors.matter_type" class="mt-1" />
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm text-gray-600 sm:col-span-2">
                        <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                        Active (available in the matter comm composer)
                    </label>
                    <div class="sm:col-span-3">
                        <InputLabel value="Subject" />
                        <TextInput
                            v-model="form.subject"
                            class="mt-1 w-full"
                            :placeholder="subjectPlaceholder"
                        />
                        <InputError :message="form.errors.subject" class="mt-1" />
                    </div>
                    <div class="sm:col-span-3">
                        <InputLabel value="Body *" />
                        <TextareaInput v-model="form.body" class="mt-1 font-mono" rows="16" />
                        <InputError :message="form.errors.body" class="mt-1" />
                    </div>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
                <Link :href="route('templates.index')" class="text-sm text-gray-600 hover:underline">
                    Cancel
                </Link>
            </div>
        </div>

        <!-- Merge field reference -->
        <aside class="h-fit rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-2 font-semibold text-gray-800">Merge Fields</h3>
            <p class="mb-3 text-xs text-gray-500">
                Insert these into the subject or body; they are replaced with the
                matter's values when the communication is generated.
            </p>
            <ul class="space-y-1">
                <li v-for="field in mergeFields" :key="field">
                    <code
                        class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-indigo-700"
                        v-text="asMergeTag(field)"
                    />
                </li>
            </ul>
        </aside>
    </form>
</template>
