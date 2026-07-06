<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    matter: Object,
    documents: Array,
    categories: Array,
    templates: Array,
});

const sourceSeverity = { office: 'info', generated: 'success', upload: 'secondary' };

// --- Upload ---
const showUpload = ref(false);
const uploadForm = useForm({ file: null, title: '', category: 'other' });

const submitUpload = () =>
    uploadForm.post(route('matters.documents.store', props.matter.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            showUpload.value = false;
        },
    });

// --- Generate from template ---
const showGenerate = ref(false);
const generateForm = useForm({ comm_template_id: '', title: '' });

const submitGenerate = () =>
    generateForm.post(route('matters.documents.generate', props.matter.id), {
        preserveScroll: true,
        onSuccess: () => {
            generateForm.reset();
            showGenerate.value = false;
        },
    });

// --- Rename / recategorise ---
const editing = ref(null);
const editForm = useForm({ title: '', category: 'other' });

const openEdit = (document) => {
    editing.value = document;
    editForm.title = document.title;
    editForm.category = document.category;
};

const submitEdit = () =>
    editForm.patch(route('documents.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });

// --- Replace (new version) ---
const replaceTarget = ref(null);
const replaceInput = ref(null);

const pickReplacement = (document) => {
    replaceTarget.value = document;
    replaceInput.value.click();
};

const submitReplacement = (event) => {
    const file = event.target.files[0];
    if (!file || !replaceTarget.value) return;
    router.post(
        route('documents.replace', replaceTarget.value.id),
        { file },
        { forceFormData: true, preserveScroll: true }
    );
    event.target.value = '';
};

const confirmDelete = useDeleteConfirm();

const destroy = (document) =>
    confirmDelete(`Delete document “${document.title}”?`, () =>
        router.delete(route('documents.destroy', document.id), { preserveScroll: true })
    );
</script>

<template>
    <div class="rounded-lg bg-white p-6 shadow-sm" data-testid="documents-panel">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Documents</h3>
                <p class="text-sm text-gray-500">
                    Uploads, files auto-filed from office messages, and PDFs generated from
                    templates. Replacing a file keeps the old version on record.
                </p>
            </div>
            <div class="flex gap-2">
                <SecondaryButton @click="showGenerate = true">Generate from Template</SecondaryButton>
                <PrimaryButton @click="showUpload = true">Upload Document</PrimaryButton>
            </div>
        </div>

        <input ref="replaceInput" type="file" class="hidden" @change="submitReplacement" />

        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                    <th class="py-2 pr-4">Title</th>
                    <th class="py-2 pr-4">Category</th>
                    <th class="py-2 pr-4">Source</th>
                    <th class="py-2 pr-4">Size</th>
                    <th class="py-2 pr-4">Added</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="document in documents" :key="document.id">
                    <td class="py-2.5 pr-4">
                        <span class="font-medium text-gray-800">{{ document.title }}</span>
                        <span
                            v-if="document.version > 1"
                            class="ml-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600"
                            >v{{ document.version }}</span
                        >
                        <div class="text-xs text-gray-400">{{ document.filename }}</div>
                    </td>
                    <td class="py-2.5 pr-4">
                        <Tag :value="document.category_label" severity="secondary" class="!text-xs" />
                    </td>
                    <td class="py-2.5 pr-4">
                        <Tag
                            :value="document.source"
                            :severity="sourceSeverity[document.source] ?? 'secondary'"
                            class="!text-xs capitalize"
                        />
                    </td>
                    <td class="py-2.5 pr-4 text-gray-600">{{ document.size }}</td>
                    <td class="py-2.5 pr-4 text-gray-600">
                        {{ document.uploaded_by }}
                        <div class="text-xs text-gray-400">{{ document.created_at.substring(0, 10) }}</div>
                    </td>
                    <td class="py-2.5 text-right text-xs whitespace-nowrap">
                        <a
                            :href="route('documents.download', document.id)"
                            class="text-indigo-600 hover:underline"
                            >Download</a
                        >
                        <button type="button" class="ml-3 text-gray-600 hover:underline" @click="openEdit(document)">
                            Edit
                        </button>
                        <button
                            type="button"
                            class="ml-3 text-gray-600 hover:underline"
                            @click="pickReplacement(document)"
                        >
                            Replace
                        </button>
                        <button type="button" class="ml-3 text-red-600 hover:underline" @click="destroy(document)">
                            Delete
                        </button>
                    </td>
                </tr>
                <tr v-if="!documents.length">
                    <td colspan="6" class="py-8 text-center text-gray-500">
                        No documents on this matter yet.
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Upload modal -->
        <Dialog v-model:visible="showUpload" modal header="Upload Document" :style="{ width: '30rem' }">
            <form class="space-y-4" @submit.prevent="submitUpload">
                <div>
                    <InputLabel value="File *" />
                    <input
                        type="file"
                        class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        @change="uploadForm.file = $event.target.files[0]"
                    />
                    <InputError :message="uploadForm.errors.file" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Title" />
                    <TextInput v-model="uploadForm.title" class="mt-1 w-full" placeholder="Defaults to the file name" />
                </div>
                <div>
                    <InputLabel value="Category *" />
                    <SelectInput v-model="uploadForm.category" :options="categories" class="mt-1 w-full" />
                    <InputError :message="uploadForm.errors.category" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showUpload = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="uploadForm.processing || !uploadForm.file">Upload</PrimaryButton>
                </div>
            </form>
        </Dialog>

        <!-- Generate modal -->
        <Dialog
            v-model:visible="showGenerate"
            modal
            header="Generate Document from Template"
            :style="{ width: '30rem' }"
        >
            <form class="space-y-4" @submit.prevent="submitGenerate">
                <p class="text-sm text-gray-500">
                    Merge fields resolve against this matter and the result is filed as a PDF.
                </p>
                <div>
                    <InputLabel value="Template *" />
                    <SelectInput
                        v-model="generateForm.comm_template_id"
                        :options="templates.map((t) => ({ value: t.id, label: t.name }))"
                        class="mt-1 w-full"
                    />
                    <InputError :message="generateForm.errors.comm_template_id" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Document title" />
                    <TextInput
                        v-model="generateForm.title"
                        class="mt-1 w-full"
                        placeholder="Defaults to the rendered subject"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showGenerate = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="generateForm.processing || !generateForm.comm_template_id">
                        Generate PDF
                    </PrimaryButton>
                </div>
            </form>
        </Dialog>

        <!-- Edit modal -->
        <Dialog
            :visible="editing !== null"
            modal
            header="Edit Document"
            :style="{ width: '28rem' }"
            @update:visible="editing = null"
        >
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <InputLabel value="Title *" />
                    <TextInput v-model="editForm.title" class="mt-1 w-full" />
                    <InputError :message="editForm.errors.title" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Category *" />
                    <SelectInput v-model="editForm.category" :options="categories" class="mt-1 w-full" />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="editing = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Dialog>
    </div>
</template>
