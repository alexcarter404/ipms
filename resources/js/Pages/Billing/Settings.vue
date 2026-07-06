<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import Tabs from 'primevue/tabs';
import { Head, router, useForm } from '@inertiajs/vue3';
import { onUnmounted, reactive, ref, watch } from 'vue';
import { useDeleteConfirm } from '@/composables/useDeleteConfirm';

const props = defineProps({
    baseCurrency: String,
    currencies: Array,
    exchangeRates: Array,
    taxRates: Array,
    activityCodes: Array,
    activityCodeOptions: Array,
    rateCards: Object,
    rateRuleFilters: Object,
    users: Array,
    timekeepers: Array,
    roles: Array,
    matterTypes: Array,
    clients: Array,
});

const activeTab = ref(
    Object.values(props.rateRuleFilters ?? {}).some((v) => v) ? 'cards' : 'rates'
);

const shortDate = (value) =>
    value
        ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';

const confirmDelete = useDeleteConfirm();

// --- exchange rates ---
const rateForm = useForm({
    currency_code: '',
    rate: '',
    rate_date: new Date().toISOString().slice(0, 10),
});

const saveRate = () =>
    rateForm.post(route('billing.exchange-rates.save'), {
        preserveScroll: true,
        onSuccess: () => rateForm.reset(),
    });

const syncRates = () => router.post(route('billing.sync-rates'), {}, { preserveScroll: true });

// --- tax rates ---
const taxForm = useForm({ id: null, name: '', rate: '', country_code: '', is_default: false });

const editTax = (tax) => {
    taxForm.id = tax.id;
    taxForm.name = tax.name;
    taxForm.rate = Number(tax.rate);
    taxForm.country_code = tax.country_code ?? '';
    taxForm.is_default = tax.is_default;
};

const saveTax = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => taxForm.reset(),
    };
    const transform = (d) => ({ ...d, country_code: d.country_code || null });
    taxForm.id
        ? taxForm.transform(transform).patch(route('billing.tax-rates.update', taxForm.id), options)
        : taxForm.transform(transform).post(route('billing.tax-rates.store'), options);
};

const deleteTax = (tax) =>
    confirmDelete(`Delete tax rate “${tax.name}”?`, () =>
        router.delete(route('billing.tax-rates.destroy', tax.id), { preserveScroll: true })
    );

// --- activity codes ---
const codeForm = useForm({ id: null, code: '', description: '' });

const editCode = (code) => {
    codeForm.id = code.id;
    codeForm.code = code.code;
    codeForm.description = code.description;
};

const saveCode = () => {
    const options = { preserveScroll: true, onSuccess: () => codeForm.reset() };
    codeForm.id
        ? codeForm.patch(route('billing.activity-codes.update', codeForm.id), options)
        : codeForm.post(route('billing.activity-codes.store'), options);
};

const deleteCode = (code) =>
    confirmDelete(`Delete activity code ${code.code}?`, () =>
        router.delete(route('billing.activity-codes.destroy', code.id), { preserveScroll: true })
    );

// --- rate cards ---
const cardForm = useForm({
    id: null,
    user_id: '',
    role: '',
    client_id: '',
    matter_type: '',
    activity_code_id: '',
    currency_code: props.baseCurrency,
    hourly_rate: '',
    effective_from: new Date().toISOString().slice(0, 10),
});

const editCard = (card) => {
    cardForm.id = card.id;
    cardForm.user_id = card.user_id ?? '';
    cardForm.role = card.role ?? '';
    cardForm.client_id = card.client_id ?? '';
    cardForm.matter_type = card.matter_type ?? '';
    cardForm.activity_code_id = card.activity_code_id ?? '';
    cardForm.currency_code = card.currency_code;
    cardForm.hourly_rate = Number(card.hourly_rate);
    cardForm.effective_from = card.effective_from.slice(0, 10);
};

const saveCard = () => {
    const options = { preserveScroll: true, onSuccess: () => cardForm.reset() };
    const transform = (d) => ({
        ...d,
        user_id: d.user_id || null,
        role: d.role || null,
        client_id: d.client_id || null,
        matter_type: d.matter_type || null,
        activity_code_id: d.activity_code_id || null,
    });
    cardForm.id
        ? cardForm.transform(transform).patch(route('billing.rate-cards.update', cardForm.id), options)
        : cardForm.transform(transform).post(route('billing.rate-cards.store'), options);
};

const deleteCard = (card) =>
    confirmDelete('Delete this rate rule?', () =>
        router.delete(route('billing.rate-cards.destroy', card.id), { preserveScroll: true })
    );

// --- rule browsing: server-side search, filter, sort, pagination ---
const ruleQuery = reactive({
    rr_search: props.rateRuleFilters?.search ?? '',
    rr_role: props.rateRuleFilters?.role ?? '',
    rr_type: props.rateRuleFilters?.matter_type ?? '',
    rr_sort: props.rateRuleFilters?.sort ?? '',
    rr_dir: props.rateRuleFilters?.dir ?? '',
});

const reloadRules = (extra = {}) => {
    const params = Object.fromEntries(
        Object.entries({ ...ruleQuery, ...extra }).filter(([, v]) => v !== '' && v !== null)
    );
    router.get(route('billing.settings'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['rateCards', 'rateRuleFilters'],
    });
};

let ruleTimeout = null;
watch(ruleQuery, () => {
    clearTimeout(ruleTimeout);
    ruleTimeout = setTimeout(() => reloadRules(), 300);
});

onUnmounted(() => clearTimeout(ruleTimeout));

const onRulePage = (event) => reloadRules({ rr_page: event.page + 1 });

const onRuleSort = (event) => {
    ruleQuery.rr_sort = event.sortField ?? '';
    ruleQuery.rr_dir = event.sortOrder === 1 ? 'asc' : 'desc';
};

const roleLabel = (value) => props.roles.find((r) => r.value === value)?.label ?? value;
const typeLabel = (value) => props.matterTypes.find((t) => t.value === value)?.label ?? value;

const whoLabel = (card) => {
    if (card.user) return card.user.name;
    if (card.role) return `Any ${roleLabel(card.role)}`;
    return 'Any timekeeper';
};

const setGrade = (timekeeper, role) =>
    router.patch(
        route('billing.timekeepers.role', timekeeper.id),
        { role: role || null },
        { preserveScroll: true }
    );
</script>

<template>
    <Head title="Billing Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Billing Settings</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Exchange rates, tax treatments, task codes, timekeeper grades and rate rules.
                    Base currency: <strong>{{ baseCurrency }}</strong>.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <Tabs v-model:value="activeTab" :pt="{ root: { class: '!bg-transparent' } }">
                <TabList :pt="{ tabList: { class: '!bg-transparent' } }">
                    <Tab value="rates">Exchange Rates</Tab>
                    <Tab value="tax">Tax Rates</Tab>
                    <Tab value="codes">Activity Codes</Tab>
                    <Tab value="cards">Rate Rules</Tab>
                </TabList>
            </Tabs>

            <!-- Exchange rates -->
            <div v-if="activeTab === 'rates'" class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Currency</th>
                                    <th class="px-4 py-3 text-right">1 {{ baseCurrency }} =</th>
                                    <th class="px-4 py-3">As of</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="rate in exchangeRates" :key="rate.id">
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ rate.currency_code }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700">{{ Number(rate.rate) }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ shortDate(rate.rate_date) }}</td>
                                </tr>
                                <tr v-if="!exchangeRates.length">
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-500">
                                        No rates stored — sync from the provider or add one manually.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <SecondaryButton class="mt-3" @click="syncRates">Sync rates from provider</SecondaryButton>
                    <p class="mt-1 text-xs text-gray-500">
                        Rates also refresh automatically every weekday afternoon.
                    </p>
                </div>

                <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="saveRate">
                    <h4 class="font-semibold text-gray-800">Set a rate manually</h4>
                    <div>
                        <InputLabel value="Currency" />
                        <SelectInput
                            v-model="rateForm.currency_code"
                            :options="currencies.filter((c) => c.value !== baseCurrency)"
                            placeholder="Select…"
                            class="mt-1"
                        />
                        <InputError :message="rateForm.errors.currency_code" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="`1 ${baseCurrency} equals`" />
                        <TextInput v-model="rateForm.rate" type="number" step="0.000001" class="mt-1 w-full" />
                        <InputError :message="rateForm.errors.rate" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Rate date" />
                        <DateInput v-model="rateForm.rate_date" class="mt-1" />
                        <InputError :message="rateForm.errors.rate_date" class="mt-1" />
                    </div>
                    <PrimaryButton :disabled="rateForm.processing">Save Rate</PrimaryButton>
                </form>
            </div>

            <!-- Tax rates -->
            <div v-else-if="activeTab === 'tax'" class="grid gap-6 lg:grid-cols-3">
                <div class="overflow-x-auto rounded-lg bg-white shadow-sm lg:col-span-2">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3 text-right">Rate</th>
                                <th class="px-4 py-3">Country</th>
                                <th class="px-4 py-3">Default</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="tax in taxRates" :key="tax.id">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ tax.name }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ Number(tax.rate) }}%</td>
                                <td class="px-4 py-3 text-gray-600">{{ tax.country_code ?? '—' }}</td>
                                <td class="px-4 py-3">{{ tax.is_default ? '✓' : '' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                    <button class="text-indigo-600 hover:underline" @click="editTax(tax)">Edit</button>
                                    <button class="ml-2 text-red-600 hover:underline" @click="deleteTax(tax)">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="saveTax">
                    <h4 class="font-semibold text-gray-800">{{ taxForm.id ? 'Edit tax rate' : 'New tax rate' }}</h4>
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="taxForm.name" class="mt-1 w-full" placeholder="e.g. UK VAT (standard)" />
                        <InputError :message="taxForm.errors.name" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Rate %" />
                            <TextInput v-model="taxForm.rate" type="number" step="0.01" class="mt-1 w-full" />
                            <InputError :message="taxForm.errors.rate" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Country" />
                            <TextInput v-model="taxForm.country_code" maxlength="2" placeholder="GB" class="mt-1 w-full uppercase" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="taxForm.is_default" type="checkbox" class="rounded text-indigo-600" />
                        Default for new entities
                    </label>
                    <div class="flex gap-2">
                        <PrimaryButton :disabled="taxForm.processing">Save</PrimaryButton>
                        <SecondaryButton v-if="taxForm.id" type="button" @click="taxForm.reset()">Cancel</SecondaryButton>
                    </div>
                </form>
            </div>

            <!-- Activity codes -->
            <div v-else-if="activeTab === 'codes'" class="grid gap-6 lg:grid-cols-3">
                <div class="overflow-x-auto rounded-lg bg-white shadow-sm lg:col-span-2">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="code in activityCodes" :key="code.id">
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-800">{{ code.code }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ code.description }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-xs">
                                    <button class="text-indigo-600 hover:underline" @click="editCode(code)">Edit</button>
                                    <button class="ml-2 text-red-600 hover:underline" @click="deleteCode(code)">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="saveCode">
                    <h4 class="font-semibold text-gray-800">{{ codeForm.id ? 'Edit code' : 'New code' }}</h4>
                    <div>
                        <InputLabel value="Code" />
                        <TextInput v-model="codeForm.code" class="mt-1 w-full" placeholder="P300" />
                        <InputError :message="codeForm.errors.code" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Description" />
                        <TextInput v-model="codeForm.description" class="mt-1 w-full" />
                        <InputError :message="codeForm.errors.description" class="mt-1" />
                    </div>
                    <div class="flex gap-2">
                        <PrimaryButton :disabled="codeForm.processing">Save</PrimaryButton>
                        <SecondaryButton v-if="codeForm.id" type="button" @click="codeForm.reset()">Cancel</SecondaryButton>
                    </div>
                </form>
            </div>

            <!-- Rate cards -->
            <div v-else-if="activeTab === 'cards'" class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <!-- Search & filters -->
                    <div class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-3">
                        <TextInput
                            v-model="ruleQuery.rr_search"
                            type="search"
                            placeholder="Search timekeeper, client, code…"
                            class="w-full"
                        />
                        <SelectInput v-model="ruleQuery.rr_role" :options="roles" placeholder="All grades" />
                        <SelectInput v-model="ruleQuery.rr_type" :options="matterTypes" placeholder="All matter types" />
                    </div>

                    <DataTable
                        :value="rateCards.data"
                        lazy
                        paginator
                        :rows="rateCards.per_page"
                        :total-records="rateCards.total"
                        :first="(rateCards.current_page - 1) * rateCards.per_page"
                        :sort-field="ruleQuery.rr_sort || null"
                        :sort-order="ruleQuery.rr_dir === 'asc' ? 1 : -1"
                        data-key="id"
                        size="small"
                        class="overflow-hidden rounded-lg shadow-sm"
                        @page="onRulePage"
                        @sort="onRuleSort"
                    >
                        <template #empty>
                            <p class="py-4 text-center text-gray-500">
                                No rate rules match — time cannot be valued until one exists.
                            </p>
                        </template>

                        <Column header="Applies to">
                            <template #body="{ data }">
                                <span class="whitespace-nowrap font-medium text-gray-800">{{ whoLabel(data) }}</span>
                            </template>
                        </Column>
                        <Column header="Client">
                            <template #body="{ data }">
                                <span class="block max-w-[10rem] truncate text-gray-600">{{ data.client?.name ?? 'All' }}</span>
                            </template>
                        </Column>
                        <Column header="Matter type">
                            <template #body="{ data }">
                                <span class="whitespace-nowrap text-gray-600">{{ data.matter_type ? typeLabel(data.matter_type) : 'All' }}</span>
                            </template>
                        </Column>
                        <Column header="Task code">
                            <template #body="{ data }">
                                <span class="whitespace-nowrap text-gray-600">{{ data.activity_code?.code ?? 'All' }}</span>
                            </template>
                        </Column>
                        <Column field="hourly_rate" header="Hourly rate" sortable>
                            <template #body="{ data }">
                                <span class="whitespace-nowrap font-medium text-gray-800">
                                    {{ data.currency_code }} {{ Number(data.hourly_rate).toFixed(2) }}
                                </span>
                            </template>
                        </Column>
                        <Column field="effective_from" header="From" sortable>
                            <template #body="{ data }">
                                <span class="whitespace-nowrap text-gray-600">{{ shortDate(data.effective_from) }}</span>
                            </template>
                        </Column>
                        <Column>
                            <template #body="{ data }">
                                <span class="whitespace-nowrap text-right text-xs">
                                    <button class="text-indigo-600 hover:underline" @click="editCard(data)">Edit</button>
                                    <button class="ml-2 text-red-600 hover:underline" @click="deleteCard(data)">Delete</button>
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                    <p class="-mt-4 text-xs text-gray-500">
                        Rules are listed most-specific first and resolve in that order:
                        a personal rate beats a grade rate, which beats client, matter-type
                        and task-code scoping. Ties go to the most recent effective date.
                    </p>

                    <!-- Timekeeper grades -->
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h4 class="font-semibold text-gray-800">Timekeeper grades</h4>
                        <p class="mb-3 mt-1 text-xs text-gray-500">
                            Grades drive grade-based rate rules — new joiners get the
                            right rate without a personal rule.
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="timekeeper in timekeepers"
                                :key="timekeeper.id"
                                class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2"
                            >
                                <span class="text-sm text-gray-700">{{ timekeeper.name }}</span>
                                <SelectInput
                                    :model-value="timekeeper.role ?? ''"
                                    :options="roles"
                                    placeholder="No grade"
                                    class="w-44"
                                    @update:model-value="setGrade(timekeeper, $event)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <form class="h-fit space-y-3 rounded-lg bg-white p-4 shadow-sm" @submit.prevent="saveCard">
                    <h4 class="font-semibold text-gray-800">{{ cardForm.id ? 'Edit rate rule' : 'New rate rule' }}</h4>
                    <p class="text-xs text-gray-500">
                        Leave a dimension blank to match anything.
                    </p>
                    <div>
                        <InputLabel value="Timekeeper" />
                        <SelectInput
                            v-model="cardForm.user_id"
                            :options="users.map((u) => ({ value: u.id, label: u.name }))"
                            placeholder="Any timekeeper"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Grade" />
                        <SelectInput
                            v-model="cardForm.role"
                            :options="roles"
                            placeholder="Any grade"
                            class="mt-1"
                        />
                        <InputError :message="cardForm.errors.role" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Client" />
                        <SelectInput
                            v-model="cardForm.client_id"
                            :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                            placeholder="All clients"
                            class="mt-1"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Matter type" />
                            <SelectInput v-model="cardForm.matter_type" :options="matterTypes" placeholder="All" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Task code" />
                            <SelectInput v-model="cardForm.activity_code_id" :options="activityCodeOptions" placeholder="All" class="mt-1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Currency" />
                            <SelectInput v-model="cardForm.currency_code" :options="currencies" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Hourly rate" />
                            <TextInput v-model="cardForm.hourly_rate" type="number" step="0.01" class="mt-1 w-full" />
                            <InputError :message="cardForm.errors.hourly_rate" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Effective from" />
                        <DateInput v-model="cardForm.effective_from" class="mt-1" />
                        <InputError :message="cardForm.errors.effective_from" class="mt-1" />
                    </div>
                    <div class="flex gap-2">
                        <PrimaryButton :disabled="cardForm.processing">Save</PrimaryButton>
                        <SecondaryButton v-if="cardForm.id" type="button" @click="cardForm.reset()">Cancel</SecondaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
