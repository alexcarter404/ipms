<script setup>
import DueDate from '@/Components/DueDate.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    matter: Object,
    renewalRule: { type: Object, default: null },
});

const generate = () =>
    router.post(route('matters.renewals.generate', props.matter.id), {}, { preserveScroll: true });

const setStatus = (renewal, status) =>
    router.patch(route('renewals.update', renewal.id), { status }, { preserveScroll: true });

const remove = (renewal) => {
    if (!confirm(`Delete renewal cycle ${renewal.cycle}?`)) return;
    router.delete(route('renewals.destroy', renewal.id), { preserveScroll: true });
};

// manual add
const showAdd = ref(false);
const form = useForm({
    cycle: '',
    due_date: '',
    grace_date: '',
    official_fee: '',
    service_fee: '',
    currency: 'USD',
    notes: '',
});

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            grace_date: d.grace_date || null,
            official_fee: d.official_fee || null,
            service_fee: d.service_fee || null,
            notes: d.notes || null,
        }))
        .post(route('matters.renewals.store', props.matter.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                showAdd.value = false;
            },
        });
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">
                <template v-if="renewalRule">
                    Governed by
                    <Link
                        :href="route('renewal-rules.edit', renewalRule.id)"
                        class="font-medium text-indigo-600 hover:underline"
                    >
                        {{ renewalRule.name }}
                    </Link>
                    <span class="text-gray-500"> — {{ renewalRule.summary }}</span>
                </template>
                <template v-else>
                    No renewal rule matches this {{ matter.matter_type }} /
                    {{ matter.country_code }} —
                    <Link :href="route('renewal-rules.create')" class="text-indigo-600 hover:underline">
                        add one
                    </Link>
                    to enable schedule generation.
                </template>
            </p>
            <div class="flex gap-2">
                <SecondaryButton @click="showAdd = !showAdd">Add Manually</SecondaryButton>
                <PrimaryButton @click="generate">Generate Schedule</PrimaryButton>
            </div>
        </div>

        <!-- Manual add -->
        <form
            v-if="showAdd"
            class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-3 lg:grid-cols-6"
            @submit.prevent="submit"
        >
            <div>
                <InputLabel value="Cycle" />
                <TextInput v-model="form.cycle" type="number" min="1" class="mt-1 w-full" />
                <InputError :message="form.errors.cycle" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Due date" />
                <TextInput v-model="form.due_date" type="date" class="mt-1 w-full" />
                <InputError :message="form.errors.due_date" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Grace date" />
                <TextInput v-model="form.grace_date" type="date" class="mt-1 w-full" />
                <InputError :message="form.errors.grace_date" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Official fee" />
                <TextInput v-model="form.official_fee" type="number" step="0.01" class="mt-1 w-full" />
            </div>
            <div>
                <InputLabel value="Service fee" />
                <TextInput v-model="form.service_fee" type="number" step="0.01" class="mt-1 w-full" />
            </div>
            <div>
                <InputLabel value="Currency" />
                <TextInput v-model="form.currency" maxlength="3" class="mt-1 w-full uppercase" />
                <InputError :message="form.errors.currency" class="mt-1" />
            </div>
            <div class="sm:col-span-3 lg:col-span-6">
                <PrimaryButton :disabled="form.processing">Save Renewal</PrimaryButton>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Cycle</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Grace</th>
                        <th class="px-4 py-3">Fees</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="renewal in matter.renewals" :key="renewal.id">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ renewal.cycle }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <DueDate
                                :date="renewal.due_date"
                                :highlight="['upcoming', 'reminder_sent', 'instructed'].includes(renewal.status)"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-500">
                            <DueDate :date="renewal.grace_date" :highlight="false" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                            <template v-if="renewal.official_fee || renewal.service_fee">
                                {{ renewal.currency }}
                                {{ (Number(renewal.official_fee ?? 0) + Number(renewal.service_fee ?? 0)).toFixed(2) }}
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-4 py-3"><StatusBadge :status="renewal.status" /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                            <template v-if="['upcoming', 'reminder_sent', 'instructed'].includes(renewal.status)">
                                <button
                                    v-if="renewal.status === 'upcoming'"
                                    class="text-sky-700 hover:underline"
                                    @click="setStatus(renewal, 'reminder_sent')"
                                >
                                    Reminder Sent
                                </button>
                                <button
                                    v-if="renewal.status !== 'instructed'"
                                    class="ml-2 text-indigo-600 hover:underline"
                                    @click="setStatus(renewal, 'instructed')"
                                >
                                    Instructed
                                </button>
                                <button
                                    class="ml-2 text-green-700 hover:underline"
                                    @click="setStatus(renewal, 'paid')"
                                >
                                    Paid
                                </button>
                                <button
                                    class="ml-2 text-gray-500 hover:underline"
                                    @click="setStatus(renewal, 'lapsed')"
                                >
                                    Lapse
                                </button>
                                <button
                                    class="ml-2 text-gray-500 hover:underline"
                                    @click="setStatus(renewal, 'waived')"
                                >
                                    Waive
                                </button>
                            </template>
                            <button class="ml-2 text-red-600 hover:underline" @click="remove(renewal)">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!matter.renewals.length">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            No renewals — generate the schedule from the matter's key dates, or add one manually.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
