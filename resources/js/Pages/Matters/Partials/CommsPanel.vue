<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    matter: Object,
    templates: Array,
});

const showComposer = ref(false);
const loadingPreview = ref(false);
const expanded = ref(null);

const form = useForm({
    comm_template_id: '',
    channel: 'email',
    recipient_name: '',
    recipient_email: '',
    subject: '',
    body: '',
});

const applyRecipient = (contact) => {
    if (!contact) return;
    form.recipient_name = contact.name;
    form.recipient_email = contact.email ?? '';
};

const selectedRecipient = ref('');

const pickRecipient = () => {
    const contact = props.matter.contacts.find(
        (c) => `${c.id}-${c.pivot.role}` === selectedRecipient.value
    );
    applyRecipient(contact);
};

const openComposer = () => {
    form.reset();
    const main = props.matter.contacts.find((c) => c.pivot.role === 'main');
    applyRecipient(main);
    if (!main) form.recipient_name = props.matter.client?.name ?? '';
    selectedRecipient.value = main ? `${main.id}-${main.pivot.role}` : '';
    showComposer.value = true;
};

const loadTemplate = async () => {
    if (!form.comm_template_id) return;
    loadingPreview.value = true;
    try {
        const { data } = await window.axios.post(route('templates.preview'), {
            template_id: form.comm_template_id,
            matter_id: props.matter.id,
        });
        form.subject = data.subject;
        form.body = data.body;
        form.channel = data.channel;
    } finally {
        loadingPreview.value = false;
    }
};

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            comm_template_id: d.comm_template_id || null,
            recipient_email: d.recipient_email || null,
        }))
        .post(route('matters.communications.store', props.matter.id), {
            preserveScroll: true,
            onSuccess: () => {
                showComposer.value = false;
                form.reset();
            },
        });

const markSent = (comm) =>
    router.post(route('communications.send', comm.id), {}, { preserveScroll: true });

const confirmDelete = useDeleteConfirm();

const remove = (comm) =>
    confirmDelete('Delete this draft?', () =>
        router.delete(route('communications.destroy', comm.id), { preserveScroll: true }));

const formatDateTime = (value) =>
    value ? new Date(value).toLocaleString() : '';
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">
                Letters and emails generated for this matter.
            </p>
            <PrimaryButton @click="openComposer">Compose</PrimaryButton>
        </div>

        <div class="space-y-3">
            <div
                v-for="comm in matter.communications"
                :key="comm.id"
                class="rounded-lg bg-white p-4 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-800">
                            {{ comm.subject || '(no subject)' }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ comm.channel }} · to {{ comm.recipient_name || comm.recipient_email || '—' }}
                            <template v-if="comm.template"> · from “{{ comm.template.name }}”</template>
                            · {{ formatDateTime(comm.created_at) }}
                            <template v-if="comm.creator"> · by {{ comm.creator.name }}</template>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <StatusBadge :status="comm.status" />
                        <button
                            class="text-indigo-600 hover:underline"
                            @click="expanded = expanded === comm.id ? null : comm.id"
                        >
                            {{ expanded === comm.id ? 'Hide' : 'View' }}
                        </button>
                        <template v-if="comm.status === 'draft'">
                            <button class="text-green-700 hover:underline" @click="markSent(comm)">
                                Mark Sent
                            </button>
                            <button class="text-red-600 hover:underline" @click="remove(comm)">
                                Delete
                            </button>
                        </template>
                    </div>
                </div>
                <pre
                    v-if="expanded === comm.id"
                    class="mt-3 whitespace-pre-wrap rounded-md bg-gray-50 p-3 font-sans text-sm text-gray-700"
                    >{{ comm.body }}</pre
                >
            </div>
            <p
                v-if="!matter.communications.length"
                class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow-sm"
            >
                No communications yet.
            </p>
        </div>

        <!-- Composer modal -->
        <Modal :show="showComposer" max-width="2xl" @close="showComposer = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Compose Communication</h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <InputLabel value="From template" />
                        <div class="mt-1 flex gap-2">
                            <SelectInput
                                v-model="form.comm_template_id"
                                :options="templates.map((t) => ({ value: t.id, label: `${t.name} (${t.channel})` }))"
                                placeholder="Blank"
                                class="flex-1"
                            />
                            <SecondaryButton
                                :disabled="!form.comm_template_id || loadingPreview"
                                @click="loadTemplate"
                            >
                                {{ loadingPreview ? 'Loading…' : 'Load' }}
                            </SecondaryButton>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Loading fills subject &amp; body with merge fields resolved for this matter.
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Channel" />
                        <SelectInput
                            v-model="form.channel"
                            :options="[
                                { value: 'email', label: 'Email' },
                                { value: 'letter', label: 'Letter' },
                            ]"
                            class="mt-1"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Send to matter contact" />
                        <SelectInput
                            v-model="selectedRecipient"
                            :options="matter.contacts.map((c) => ({
                                value: `${c.id}-${c.pivot.role}`,
                                label: `${c.name} (${c.pivot.role})${c.email ? ' — ' + c.email : ''}`,
                            }))"
                            placeholder="Custom recipient"
                            class="mt-1"
                            @change="pickRecipient"
                        />
                    </div>
                    <div>
                        <InputLabel value="Recipient name" />
                        <TextInput v-model="form.recipient_name" class="mt-1 w-full" />
                        <InputError :message="form.errors.recipient_name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Recipient email" />
                        <TextInput v-model="form.recipient_email" type="email" class="mt-1 w-full" />
                        <InputError :message="form.errors.recipient_email" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel value="Subject" />
                    <TextInput v-model="form.subject" class="mt-1 w-full" />
                    <InputError :message="form.errors.subject" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Body" />
                    <TextareaInput v-model="form.body" class="mt-1" rows="10" />
                    <InputError :message="form.errors.body" class="mt-1" />
                </div>

                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showComposer = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing" @click="submit">Save Draft</PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
