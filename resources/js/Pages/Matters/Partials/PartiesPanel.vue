<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    matter: Object,
    parties: Array, // all parties for the picker
    partyRoles: Array,
});

const mode = ref('existing'); // existing | new

const form = useForm({
    party_id: '',
    name: '',
    party_type: 'individual',
    role: 'applicant',
});

const submit = () => {
    const payload = mode.value === 'existing'
        ? { party_id: form.party_id, role: form.role }
        : { name: form.name, party_type: form.party_type, role: form.role };

    form.transform(() => payload).post(route('matters.parties.store', props.matter.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('party_id', 'name'),
    });
};

const confirmDelete = useDeleteConfirm();

const remove = (party) =>
    confirmDelete(`Remove ${party.name} (${party.pivot.role}) from this matter?`, () =>
        useForm({ role: party.pivot.role }).delete(
            route('matters.parties.destroy', [props.matter.id, party.id]),
            { preserveScroll: true }
        ), 'Remove');

const grouped = () => {
    const groups = {};
    for (const party of props.matter.parties) {
        (groups[party.pivot.role] ??= []).push(party);
    }
    return groups;
};
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div
                v-for="(members, role) in grouped()"
                :key="role"
                class="rounded-lg bg-white p-4 shadow-sm"
            >
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ role }}s
                </h4>
                <ul class="divide-y">
                    <li
                        v-for="party in members"
                        :key="`${party.id}-${role}`"
                        class="flex items-center justify-between py-2 text-sm"
                    >
                        <div>
                            <span class="font-medium text-gray-800">{{ party.name }}</span>
                            <span class="ml-2 text-xs text-gray-500">{{ party.type }}</span>
                        </div>
                        <button
                            type="button"
                            class="text-xs text-red-600 hover:underline"
                            @click="remove(party)"
                        >
                            Remove
                        </button>
                    </li>
                </ul>
            </div>
            <p v-if="!matter.parties.length" class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
                No parties recorded — add applicants, inventors, agents and other parties here.
            </p>
        </div>

        <!-- Add party -->
        <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="submit">
            <h4 class="font-semibold text-gray-800">Add Party</h4>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-1.5">
                    <input v-model="mode" type="radio" value="existing" class="text-indigo-600" />
                    Existing
                </label>
                <label class="flex items-center gap-1.5">
                    <input v-model="mode" type="radio" value="new" class="text-indigo-600" />
                    New party
                </label>
            </div>

            <div v-if="mode === 'existing'">
                <InputLabel value="Party" />
                <SelectInput
                    v-model="form.party_id"
                    :options="parties.map((p) => ({ value: p.id, label: p.name }))"
                    placeholder="Select…"
                    class="mt-1"
                />
                <InputError :message="form.errors.party_id" class="mt-1" />
            </div>
            <template v-else>
                <div>
                    <InputLabel value="Name" />
                    <TextInput v-model="form.name" class="mt-1 w-full" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Party type" />
                    <SelectInput
                        v-model="form.party_type"
                        :options="[
                            { value: 'individual', label: 'Individual' },
                            { value: 'organisation', label: 'Organisation' },
                        ]"
                        class="mt-1"
                    />
                </div>
            </template>

            <div>
                <InputLabel value="Role" />
                <SelectInput v-model="form.role" :options="partyRoles" class="mt-1" />
                <InputError :message="form.errors.role" class="mt-1" />
            </div>

            <PrimaryButton :disabled="form.processing">Add</PrimaryButton>
        </form>
    </div>
</template>
