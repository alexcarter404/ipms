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
    countries: Array,
    submitLabel: { type: String, default: 'Save' },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <InputLabel value="Code *" />
                    <TextInput v-model="form.code" class="mt-1 w-full" placeholder="e.g. ACME" />
                    <InputError :message="form.errors.code" class="mt-1" />
                </div>
                <div class="lg:col-span-2">
                    <InputLabel value="Name *" />
                    <TextInput v-model="form.name" class="mt-1 w-full" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Type *" />
                    <SelectInput
                        v-model="form.type"
                        :options="[
                            { value: 'company', label: 'Company' },
                            { value: 'individual', label: 'Individual' },
                        ]"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.type" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Email" />
                    <TextInput v-model="form.email" type="email" class="mt-1 w-full" />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Phone" />
                    <TextInput v-model="form.phone" class="mt-1 w-full" />
                    <InputError :message="form.errors.phone" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Country" />
                    <SelectInput v-model="form.country_code" :options="countries" placeholder="—" class="mt-1" />
                    <InputError :message="form.errors.country_code" class="mt-1" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <InputLabel value="Notes" />
                    <TextareaInput v-model="form.notes" class="mt-1" rows="3" />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
            <Link :href="route('clients.index')" class="text-sm text-gray-600 hover:underline">
                Cancel
            </Link>
        </div>
    </form>
</template>
