<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    clientName: String,
    matters: Array,
    renewals: Array,
    documents: Array,
    invoices: Array,
});

const money = (amount, currency) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount ?? 0);

const instruct = (renewal, decision) =>
    router.post(
        route('portal.renewals.instruct', renewal.id),
        { decision },
        { preserveScroll: true }
    );
</script>

<template>
    <Head title="Client Portal" />

    <PortalLayout>
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">{{ clientName }}</h1>
                <p class="text-sm text-gray-500">
                    Your IP portfolio at a glance — read-only, except where we need your word.
                </p>
            </div>

            <!-- Renewal instructions -->
            <div class="rounded-lg bg-white p-6 shadow-sm" data-testid="portal-renewals">
                <h2 class="mb-1 font-semibold text-gray-800">Renewals awaiting your instruction</h2>
                <p class="mb-4 text-sm text-gray-500">
                    Tell us whether to pay each upcoming renewal or let the right lapse.
                </p>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        <tr v-for="renewal in renewals" :key="renewal.id">
                            <td class="py-2.5 pr-4 font-medium text-gray-800">
                                {{ renewal.matter_reference }}
                                <span class="ml-1 text-xs text-gray-400">{{ renewal.country }}</span>
                            </td>
                            <td class="py-2.5 pr-4 text-gray-600">Year {{ renewal.cycle }}</td>
                            <td class="py-2.5 pr-4 text-gray-600">due {{ renewal.due_date }}</td>
                            <td class="py-2.5 pr-4 text-gray-600">
                                {{ renewal.official_fee ? money(renewal.official_fee, renewal.currency) : '—' }}
                            </td>
                            <td class="py-2.5 text-right text-xs whitespace-nowrap">
                                <button
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-500"
                                    @click="instruct(renewal, 'pay')"
                                >
                                    Instruct Payment
                                </button>
                                <button
                                    type="button"
                                    class="ml-2 rounded-md bg-white px-3 py-1.5 font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50"
                                    @click="instruct(renewal, 'abandon')"
                                >
                                    Let Lapse
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!renewals.length">
                            <td class="py-6 text-center text-gray-500">Nothing awaiting instruction.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Portfolio -->
            <div class="rounded-lg bg-white p-6 shadow-sm" data-testid="portal-matters">
                <h2 class="mb-4 font-semibold text-gray-800">Your matters</h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-4">Reference</th>
                            <th class="py-2 pr-4">Title</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Ctry</th>
                            <th class="py-2 pr-4">Number</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="matter in matters" :key="matter.id">
                            <td class="py-2.5 pr-4 font-medium text-gray-800">{{ matter.reference }}</td>
                            <td class="max-w-xs truncate py-2.5 pr-4 text-gray-700">{{ matter.title }}</td>
                            <td class="py-2.5 pr-4 capitalize text-gray-600">{{ matter.type }}</td>
                            <td class="py-2.5 pr-4 text-gray-600">{{ matter.country }}</td>
                            <td class="py-2.5 pr-4 text-gray-600">
                                {{ matter.registration_no ?? matter.application_no ?? '—' }}
                            </td>
                            <td class="py-2.5"><StatusBadge :status="matter.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Documents -->
                <div class="rounded-lg bg-white p-6 shadow-sm" data-testid="portal-documents">
                    <h2 class="mb-4 font-semibold text-gray-800">Documents</h2>
                    <ul class="divide-y text-sm">
                        <li v-for="document in documents" :key="document.id" class="flex items-center justify-between py-2.5">
                            <div>
                                <span class="font-medium text-gray-800">{{ document.title }}</span>
                                <div class="text-xs text-gray-400">
                                    {{ document.matter_reference }} · {{ document.category }} · {{ document.created_at }}
                                </div>
                            </div>
                            <a
                                :href="route('portal.documents.download', document.id)"
                                class="text-xs text-indigo-600 hover:underline"
                                >Download</a
                            >
                        </li>
                        <li v-if="!documents.length" class="py-6 text-center text-gray-500">No documents shared yet.</li>
                    </ul>
                </div>

                <!-- Invoices -->
                <div class="rounded-lg bg-white p-6 shadow-sm" data-testid="portal-invoices">
                    <h2 class="mb-4 font-semibold text-gray-800">Invoices</h2>
                    <ul class="divide-y text-sm">
                        <li v-for="invoice in invoices" :key="invoice.number" class="flex items-center justify-between py-2.5">
                            <div>
                                <span class="font-medium text-gray-800">{{ invoice.number }}</span>
                                <div class="text-xs text-gray-400">
                                    {{ invoice.entity }} · issued {{ invoice.issued_at ?? '—' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-medium text-gray-800">{{ money(invoice.total, invoice.currency) }}</div>
                                <div class="text-xs" :class="invoice.balance > 0 ? 'text-amber-600' : 'text-green-600'">
                                    {{ invoice.balance > 0 ? `${money(invoice.balance, invoice.currency)} outstanding` : 'Paid' }}
                                </div>
                            </div>
                        </li>
                        <li v-if="!invoices.length" class="py-6 text-center text-gray-500">No invoices issued.</li>
                    </ul>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
