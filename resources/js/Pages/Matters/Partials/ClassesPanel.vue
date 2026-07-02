<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    matter: Object,
});

const form = useForm({
    class_number: '',
    specification: '',
});

const submit = () =>
    form.post(route('matters.classes.store', props.matter.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

const remove = (cls) => {
    if (!confirm(`Remove class ${cls.class_number}?`)) return;
    router.delete(route('classes.destroy', cls.id), { preserveScroll: true });
};
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="w-20 px-4 py-3">Class</th>
                            <th class="px-4 py-3">Specification</th>
                            <th class="w-20 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="cls in matter.classes" :key="cls.id">
                            <td class="px-4 py-3 font-semibold text-gray-800">
                                {{ cls.class_number }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ cls.specification ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="remove(cls)"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!matter.classes.length">
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                No classes recorded.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="submit">
            <h4 class="font-semibold text-gray-800">Add Class</h4>
            <div>
                <InputLabel value="Class number (1–45)" />
                <TextInput v-model="form.class_number" type="number" min="1" max="45" class="mt-1 w-full" />
                <InputError :message="form.errors.class_number" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Specification of goods / services" />
                <TextareaInput v-model="form.specification" class="mt-1" rows="4" />
                <InputError :message="form.errors.specification" class="mt-1" />
            </div>
            <PrimaryButton :disabled="form.processing">Add</PrimaryButton>
        </form>
    </div>
</template>
