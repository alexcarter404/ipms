<script setup>
import DueDate from '@/Components/DueDate.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import DateInput from '@/Components/DateInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    matter: Object,
    users: Array,
    priorities: Array,
    workflows: Array,
    baseDates: Object,
});

const showCompleted = ref(false);

const tasks = computed(() =>
    showCompleted.value
        ? props.matter.tasks
        : props.matter.tasks.filter((t) => ['pending', 'in_progress'].includes(t.status))
);

// --- new task form ---
const taskForm = useForm({
    title: '',
    description: '',
    due_date: '',
    internal_date: '',
    priority: 'normal',
    is_critical: false,
    assigned_to: '',
});

const submitTask = () =>
    taskForm
        .transform((d) => ({
            ...d,
            internal_date: d.internal_date || null,
            assigned_to: d.assigned_to || null,
        }))
        .post(route('matters.tasks.store', props.matter.id), {
            preserveScroll: true,
            onSuccess: () => taskForm.reset(),
        });

// --- apply workflow ---
const showWorkflowModal = ref(false);

const workflowForm = useForm({
    workflow_id: '',
    base_date: '',
    assigned_to: '',
});

const selectedWorkflow = computed(() =>
    props.workflows.find((w) => w.id === Number(workflowForm.workflow_id))
);

const defaultBaseDate = computed(() => {
    if (!selectedWorkflow.value) return null;
    return props.baseDates[selectedWorkflow.value.trigger_event] ?? null;
});

const applyWorkflow = () =>
    workflowForm
        .transform((d) => ({
            ...d,
            base_date: d.base_date || null,
            assigned_to: d.assigned_to || null,
        }))
        .post(route('matters.workflows.apply', props.matter.id), {
            preserveScroll: true,
            onSuccess: () => {
                showWorkflowModal.value = false;
                workflowForm.reset();
            },
        });

// --- task status transitions ---
const setStatus = (task, status) =>
    router.patch(route('tasks.update', task.id), { status }, { preserveScroll: true });

const confirmDelete = useDeleteConfirm();

const removeTask = (task) =>
    confirmDelete(`Delete task “${task.title}”?`, () =>
        router.delete(route('tasks.destroy', task.id), { preserveScroll: true }));
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="showCompleted" type="checkbox" class="rounded text-indigo-600" />
                Show completed / cancelled
            </label>
            <SecondaryButton v-if="workflows.length" @click="showWorkflowModal = true">
                Apply Workflow
            </SecondaryButton>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Task</th>
                                <th class="px-4 py-3">Due</th>
                                <th class="px-4 py-3">Priority</th>
                                <th class="px-4 py-3">Assignee</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="task in tasks" :key="task.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">
                                        {{ task.title }}
                                        <span
                                            v-if="task.is_critical"
                                            class="ml-1 text-xs font-semibold text-red-600"
                                            title="Statutory / critical deadline"
                                            >⚠</span
                                        >
                                    </div>
                                    <div v-if="task.description" class="mt-0.5 max-w-md text-xs text-gray-500">
                                        {{ task.description }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <DueDate :date="task.due_date" :highlight="['pending', 'in_progress'].includes(task.status)" />
                                </td>
                                <td class="px-4 py-3">
                                    <StatusBadge :status="task.priority" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                    {{ task.assignee?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <StatusBadge :status="task.status" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                    <template v-if="['pending', 'in_progress'].includes(task.status)">
                                        <button
                                            v-if="task.status === 'pending'"
                                            class="text-indigo-600 hover:underline"
                                            @click="setStatus(task, 'in_progress')"
                                        >
                                            Start
                                        </button>
                                        <button
                                            class="ml-2 text-green-700 hover:underline"
                                            @click="setStatus(task, 'completed')"
                                        >
                                            Complete
                                        </button>
                                        <button
                                            class="ml-2 text-gray-500 hover:underline"
                                            @click="setStatus(task, 'cancelled')"
                                        >
                                            Cancel
                                        </button>
                                    </template>
                                    <button
                                        class="ml-2 text-red-600 hover:underline"
                                        @click="removeTask(task)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!tasks.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No tasks — create one or apply a workflow.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- New task -->
            <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="submitTask">
                <h4 class="font-semibold text-gray-800">New Task</h4>
                <div>
                    <InputLabel value="Title" />
                    <TextInput v-model="taskForm.title" class="mt-1 w-full" />
                    <InputError :message="taskForm.errors.title" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Description" />
                    <TextareaInput v-model="taskForm.description" class="mt-1" rows="2" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Due date" />
                        <DateInput v-model="taskForm.due_date" class="mt-1" />
                        <InputError :message="taskForm.errors.due_date" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Internal date" />
                        <DateInput v-model="taskForm.internal_date" class="mt-1" />
                        <InputError :message="taskForm.errors.internal_date" class="mt-1" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Priority" />
                        <SelectInput v-model="taskForm.priority" :options="priorities" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Assignee" />
                        <SelectInput
                            v-model="taskForm.assigned_to"
                            :options="users.map((u) => ({ value: u.id, label: u.name }))"
                            placeholder="—"
                            class="mt-1"
                        />
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="taskForm.is_critical" type="checkbox" class="rounded text-indigo-600" />
                    Critical (statutory) deadline
                </label>
                <PrimaryButton :disabled="taskForm.processing">Add Task</PrimaryButton>
            </form>
        </div>

        <!-- Apply workflow modal -->
        <Modal :show="showWorkflowModal" @close="showWorkflowModal = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Apply Workflow</h3>
                <div>
                    <InputLabel value="Workflow" />
                    <SelectInput
                        v-model="workflowForm.workflow_id"
                        :options="workflows.map((w) => ({ value: w.id, label: w.name }))"
                        placeholder="Select…"
                        class="mt-1"
                    />
                    <InputError :message="workflowForm.errors.workflow_id" class="mt-1" />
                </div>
                <div v-if="selectedWorkflow" class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
                    <p class="font-medium text-gray-700">
                        Trigger: {{ selectedWorkflow.trigger_event.replace('_', ' ') }}
                        <span v-if="defaultBaseDate"> — defaults to {{ defaultBaseDate }}</span>
                    </p>
                    <ul class="mt-2 list-inside list-disc">
                        <li v-for="step in selectedWorkflow.steps" :key="step.id">
                            {{ step.title }} ({{ step.offset_value }} {{ step.offset_unit }})
                        </li>
                    </ul>
                </div>
                <div>
                    <InputLabel :value="defaultBaseDate ? 'Base date (override)' : 'Base date'" />
                    <DateInput v-model="workflowForm.base_date" class="mt-1" />
                    <InputError :message="workflowForm.errors.base_date" class="mt-1" />
                    <p v-if="!defaultBaseDate && selectedWorkflow" class="mt-1 text-xs text-amber-600">
                        The matter has no date for this trigger — enter one.
                    </p>
                </div>
                <div>
                    <InputLabel value="Assign tasks to" />
                    <SelectInput
                        v-model="workflowForm.assigned_to"
                        :options="users.map((u) => ({ value: u.id, label: u.name }))"
                        placeholder="Responsible attorney"
                        class="mt-1"
                    />
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showWorkflowModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="workflowForm.processing || !workflowForm.workflow_id" @click="applyWorkflow">
                        Apply
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
