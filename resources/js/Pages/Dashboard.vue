<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DueDate from '@/Components/DueDate.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    mattersByType: Object,
    upcomingTasks: Array,
    upcomingRenewals: Array,
    recentMatters: Array,
});

const typeLabels = {
    patent: 'Patents',
    trademark: 'Trade Marks',
    design: 'Designs',
    copyright: 'Copyright',
    domain: 'Domains',
    other: 'Other',
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Stat tiles -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <Link
                    :href="route('matters.index')"
                    class="rounded-lg bg-white p-4 shadow-sm hover:shadow"
                >
                    <div class="text-sm text-gray-500">Active Matters</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ stats.activeMatters }}
                    </div>
                </Link>
                <Link
                    :href="route('clients.index')"
                    class="rounded-lg bg-white p-4 shadow-sm hover:shadow"
                >
                    <div class="text-sm text-gray-500">Clients</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ stats.clients }}
                    </div>
                </Link>
                <Link
                    :href="route('tasks.index')"
                    class="rounded-lg bg-white p-4 shadow-sm hover:shadow"
                >
                    <div class="text-sm text-gray-500">Open Tasks</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ stats.openTasks }}
                    </div>
                </Link>
                <Link
                    :href="route('tasks.index', { overdue: 1 })"
                    class="rounded-lg bg-white p-4 shadow-sm hover:shadow"
                >
                    <div class="text-sm text-gray-500">Overdue Tasks</div>
                    <div
                        class="mt-1 text-3xl font-semibold"
                        :class="stats.overdueTasks ? 'text-red-600' : 'text-gray-900'"
                    >
                        {{ stats.overdueTasks }}
                    </div>
                </Link>
                <Link
                    :href="route('renewals.index', { due_within: 90 })"
                    class="rounded-lg bg-white p-4 shadow-sm hover:shadow"
                >
                    <div class="text-sm text-gray-500">Renewals due 90d</div>
                    <div
                        class="mt-1 text-3xl font-semibold"
                        :class="stats.renewalsDue90 ? 'text-amber-600' : 'text-gray-900'"
                    >
                        {{ stats.renewalsDue90 }}
                    </div>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Upcoming tasks -->
                <div class="rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h3 class="font-semibold text-gray-800">Upcoming Actions</h3>
                        <Link
                            :href="route('tasks.index')"
                            class="text-sm text-indigo-600 hover:underline"
                            >View all</Link
                        >
                    </div>
                    <ul class="divide-y">
                        <li
                            v-for="task in upcomingTasks"
                            :key="task.id"
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-medium text-gray-800">
                                    {{ task.title }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    <Link
                                        v-if="task.matter"
                                        :href="route('matters.show', task.matter.id)"
                                        class="text-indigo-600 hover:underline"
                                    >
                                        {{ task.matter.reference }}
                                    </Link>
                                    <span v-if="task.assignee">
                                        · {{ task.assignee.name }}</span
                                    >
                                </div>
                            </div>
                            <DueDate :date="task.due_date" class="shrink-0 text-sm" />
                        </li>
                        <li
                            v-if="!upcomingTasks.length"
                            class="px-4 py-6 text-center text-sm text-gray-500"
                        >
                            No open tasks.
                        </li>
                    </ul>
                </div>

                <!-- Upcoming renewals -->
                <div class="rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h3 class="font-semibold text-gray-800">Upcoming Renewals</h3>
                        <Link
                            :href="route('renewals.index')"
                            class="text-sm text-indigo-600 hover:underline"
                            >View all</Link
                        >
                    </div>
                    <ul class="divide-y">
                        <li
                            v-for="renewal in upcomingRenewals"
                            :key="renewal.id"
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                        >
                            <div class="min-w-0">
                                <Link
                                    v-if="renewal.matter"
                                    :href="route('matters.show', renewal.matter.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ renewal.matter.reference }}
                                </Link>
                                <span class="text-xs text-gray-500">
                                    · cycle {{ renewal.cycle }} ·
                                    {{ renewal.matter?.country_code }}
                                </span>
                                <div class="truncate text-xs text-gray-500">
                                    {{ renewal.matter?.title }}
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <StatusBadge :status="renewal.status" />
                                <DueDate :date="renewal.due_date" />
                            </div>
                        </li>
                        <li
                            v-if="!upcomingRenewals.length"
                            class="px-4 py-6 text-center text-sm text-gray-500"
                        >
                            No open renewals.
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Portfolio by type -->
                <div class="rounded-lg bg-white shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="font-semibold text-gray-800">Active Portfolio</h3>
                    </div>
                    <ul class="divide-y">
                        <li
                            v-for="(count, type) in mattersByType"
                            :key="type"
                            class="flex items-center justify-between px-4 py-2.5 text-sm"
                        >
                            <Link
                                :href="route('matters.index', { type })"
                                class="text-gray-700 hover:text-indigo-600"
                            >
                                {{ typeLabels[type] ?? type }}
                            </Link>
                            <span class="font-semibold text-gray-900">{{ count }}</span>
                        </li>
                        <li
                            v-if="!Object.keys(mattersByType).length"
                            class="px-4 py-6 text-center text-sm text-gray-500"
                        >
                            No active matters.
                        </li>
                    </ul>
                </div>

                <!-- Recent matters -->
                <div class="rounded-lg bg-white shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h3 class="font-semibold text-gray-800">Recently Added Matters</h3>
                        <Link
                            :href="route('matters.create')"
                            class="text-sm text-indigo-600 hover:underline"
                            >New matter</Link
                        >
                    </div>
                    <ul class="divide-y">
                        <li
                            v-for="matter in recentMatters"
                            :key="matter.id"
                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="route('matters.show', matter.id)"
                                    class="font-medium text-indigo-600 hover:underline"
                                >
                                    {{ matter.reference }}
                                </Link>
                                <span class="text-gray-500"> — {{ matter.title }}</span>
                                <div class="text-xs text-gray-500">
                                    {{ matter.client?.name }} · {{ matter.country_code }}
                                </div>
                            </div>
                            <StatusBadge :status="matter.status" class="shrink-0" />
                        </li>
                        <li
                            v-if="!recentMatters.length"
                            class="px-4 py-6 text-center text-sm text-gray-500"
                        >
                            No matters yet — create your first one.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
