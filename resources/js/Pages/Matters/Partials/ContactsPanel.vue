<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    matter: Object,
    clientContacts: Array,
    contactRoles: Array,
    contactTypes: Array,
});

const mode = ref('existing'); // existing | new

const form = useForm({
    contact_id: '',
    name: '',
    contact_type: 'person',
    email: '',
    role: 'main',
});

const submit = () => {
    const payload =
        mode.value === 'existing'
            ? { contact_id: form.contact_id, role: form.role }
            : {
                  name: form.name,
                  contact_type: form.contact_type,
                  email: form.email || null,
                  role: form.role,
              };

    form.transform(() => payload).post(route('matters.contacts.store', props.matter.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('contact_id', 'name', 'email'),
    });
};

const remove = (contact) => {
    if (!confirm(`Unlink ${contact.name} (${contact.pivot.role}) from this matter?`)) return;
    useForm({ role: contact.pivot.role }).delete(
        route('matters.contacts.destroy', [props.matter.id, contact.id]),
        { preserveScroll: true }
    );
};

const typeLabel = (value) =>
    props.contactTypes.find((t) => t.value === value)?.label ?? value;

const roleLabel = (value) =>
    props.contactRoles.find((r) => r.value === value)?.label ?? value;

const contactLabel = (contact) => {
    const parts = [contact.name];
    if (contact.type !== 'person') parts.push(`[${typeLabel(contact.type)}]`);
    if (contact.email) parts.push(`— ${contact.email}`);
    return parts.join(' ');
};
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role on matter</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="contact in matter.contacts"
                            :key="`${contact.id}-${contact.pivot.role}`"
                        >
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-800">{{ contact.name }}</span>
                                <div v-if="contact.position" class="text-xs text-gray-500">
                                    {{ contact.position }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        contact.type === 'person'
                                            ? 'bg-gray-100 text-gray-600'
                                            : 'bg-sky-100 text-sky-800'
                                    "
                                >
                                    {{ typeLabel(contact.type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ contact.email ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="contact.pivot.role === 'main' ? 'in_progress' : 'pending'"
                                    :label="roleLabel(contact.pivot.role)"
                                />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="remove(contact)"
                                >
                                    Unlink
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!matter.contacts.length">
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                No contacts linked — add who to correspond with,
                                where docketing goes, and who receives bills.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Link contact -->
        <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="submit">
            <h4 class="font-semibold text-gray-800">Link Contact</h4>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-1.5">
                    <input v-model="mode" type="radio" value="existing" class="text-indigo-600" />
                    Existing
                </label>
                <label class="flex items-center gap-1.5">
                    <input v-model="mode" type="radio" value="new" class="text-indigo-600" />
                    New contact
                </label>
            </div>

            <div v-if="mode === 'existing'">
                <InputLabel value="Client contact" />
                <SelectInput
                    v-model="form.contact_id"
                    :options="clientContacts.map((c) => ({ value: c.id, label: contactLabel(c) }))"
                    placeholder="Select…"
                    class="mt-1"
                />
                <InputError :message="form.errors.contact_id" class="mt-1" />
            </div>
            <template v-else>
                <div>
                    <InputLabel value="Name" />
                    <TextInput v-model="form.name" class="mt-1 w-full" placeholder="e.g. Acme IP Docketing" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Contact type" />
                    <SelectInput v-model="form.contact_type" :options="contactTypes" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Email" />
                    <TextInput v-model="form.email" type="email" class="mt-1 w-full" />
                    <InputError :message="form.errors.email" class="mt-1" />
                    <p v-if="form.contact_type === 'mailbox'" class="mt-1 text-xs text-gray-500">
                        Mailboxes need an email address.
                    </p>
                </div>
            </template>

            <div>
                <InputLabel value="Role on matter" />
                <SelectInput v-model="form.role" :options="contactRoles" class="mt-1" />
                <InputError :message="form.errors.role" class="mt-1" />
            </div>

            <PrimaryButton :disabled="form.processing">Link</PrimaryButton>
        </form>
    </div>
</template>
