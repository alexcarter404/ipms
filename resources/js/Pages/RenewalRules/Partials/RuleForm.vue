<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextareaInput from '@/Components/TextareaInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    form: Object,
    types: Array,
    countries: Array,
    submitLabel: { type: String, default: 'Save' },
});

const emit = defineEmits(['submit']);

const addOffset = () => props.form.offsets_months.push('');
const removeOffset = (i) => props.form.offsets_months.splice(i, 1);

const offsetYears = (months) =>
    months ? `≈ ${(months / 12).toFixed(1).replace(/\.0$/, '')} years` : '';

// Live preview of the cycles this rule would produce
const preview = computed(() => {
    if (props.form.schedule_mode === 'fixed') {
        const points = props.form.offsets_months.filter((m) => m);
        if (!points.length) return 'No renewals will be generated for this right.';
        return `Due ${points.map((m) => `${(m / 12).toFixed(1).replace(/\.0$/, '')}y`).join(', ')} after the ${anchorLabel.value}.`;
    }
    const { start_cycle: s, end_cycle: e, interval_years: i } = props.form;
    if (!s || !e || !i) return '';
    const first = s * i;
    const last = e * i;
    return `Cycles ${s}–${e}: due ${first}y, ${Math.min(first + i, last)}y, … ${last}y after the ${anchorLabel.value}.`;
});

const anchorLabel = computed(() =>
    props.form.base_date === 'registration' ? 'registration/grant date' : 'filing date'
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Rule</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <InputLabel value="Name *" />
                    <TextInput v-model="form.name" class="mt-1 w-full" placeholder="e.g. US Patent Maintenance Fees" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Matter type *" />
                    <SelectInput v-model="form.matter_type" :options="types" class="mt-1" />
                    <InputError :message="form.errors.matter_type" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Jurisdiction" />
                    <SelectInput
                        v-model="form.country_code"
                        :options="countries"
                        placeholder="Any country (default rule)"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.country_code" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Anchor date *" />
                    <SelectInput
                        v-model="form.base_date"
                        :options="[
                            { value: 'application', label: 'Filing date' },
                            { value: 'registration', label: 'Registration / grant date' },
                        ]"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.base_date" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Grace period (months) *" />
                    <TextInput v-model="form.grace_months" type="number" min="0" max="24" class="mt-1 w-full" />
                    <InputError :message="form.errors.grace_months" class="mt-1" />
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm text-gray-600 sm:col-span-2">
                    <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                    Active (used by the scheduler)
                </label>
            </div>
        </section>

        <!-- Schedule -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Schedule</h3>

            <div class="mb-4 flex gap-6 text-sm">
                <label class="flex items-center gap-1.5">
                    <input v-model="form.schedule_mode" type="radio" value="regular" class="text-indigo-600" />
                    Regular cycles (annuities / fixed terms)
                </label>
                <label class="flex items-center gap-1.5">
                    <input v-model="form.schedule_mode" type="radio" value="fixed" class="text-indigo-600" />
                    Fixed offsets (irregular, e.g. US maintenance fees)
                </label>
            </div>

            <div v-if="form.schedule_mode === 'regular'" class="grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel value="First cycle *" />
                    <TextInput v-model="form.start_cycle" type="number" min="1" class="mt-1 w-full" />
                    <InputError :message="form.errors.start_cycle" class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500">e.g. 2 for annuities starting in year 2</p>
                </div>
                <div>
                    <InputLabel value="Last cycle *" />
                    <TextInput v-model="form.end_cycle" type="number" min="1" class="mt-1 w-full" />
                    <InputError :message="form.errors.end_cycle" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Interval (years) *" />
                    <TextInput v-model="form.interval_years" type="number" min="1" class="mt-1 w-full" />
                    <InputError :message="form.errors.interval_years" class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500">1 = annual, 10 = ten-year terms</p>
                </div>
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="(offset, i) in form.offsets_months"
                    :key="i"
                    class="flex items-center gap-3"
                >
                    <span class="w-16 text-sm text-gray-500">Cycle {{ i + 1 }}</span>
                    <TextInput
                        v-model.number="form.offsets_months[i]"
                        type="number"
                        min="1"
                        class="w-32"
                        placeholder="Months"
                    />
                    <span class="text-xs text-gray-500">months after anchor {{ offsetYears(form.offsets_months[i]) }}</span>
                    <button
                        type="button"
                        class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                        @click="removeOffset(i)"
                    >
                        ✕
                    </button>
                </div>
                <InputError :message="form.errors.offsets_months" />
                <SecondaryButton type="button" @click="addOffset">Add Due Date</SecondaryButton>
                <p class="text-xs text-gray-500">
                    Leave empty to declare that this right has no renewals
                    (e.g. US design patents).
                </p>
            </div>

            <p v-if="preview" class="mt-4 rounded-md bg-indigo-50 p-3 text-sm text-indigo-800">
                {{ preview }}
            </p>
        </section>

        <!-- Fees -->
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-gray-800">Default Fees &amp; Notes</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel value="Official fee" />
                    <TextInput v-model="form.default_official_fee" type="number" step="0.01" min="0" class="mt-1 w-full" />
                    <InputError :message="form.errors.default_official_fee" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Service fee" />
                    <TextInput v-model="form.default_service_fee" type="number" step="0.01" min="0" class="mt-1 w-full" />
                    <InputError :message="form.errors.default_service_fee" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Currency" />
                    <TextInput v-model="form.currency" maxlength="3" class="mt-1 w-full uppercase" placeholder="USD" />
                    <InputError :message="form.errors.currency" class="mt-1" />
                </div>
                <div class="sm:col-span-3">
                    <InputLabel value="Notes" />
                    <TextareaInput v-model="form.notes" class="mt-1" rows="2" />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>
        </section>

        <div class="flex items-center gap-3">
            <PrimaryButton :disabled="form.processing">{{ submitLabel }}</PrimaryButton>
            <Link :href="route('renewal-rules.index')" class="text-sm text-gray-600 hover:underline">
                Cancel
            </Link>
        </div>
    </form>
</template>
