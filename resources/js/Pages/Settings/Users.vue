<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Tag from 'primevue/tag';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    users: Array,
    accessRoles: Array,
    grades: Array,
});

const update = (user, field, value) =>
    router.patch(
        route('users.update', user.id),
        { access_role: user.access_role, role: user.role, [field]: value },
        { preserveScroll: true }
    );
</script>

<template>
    <Head title="Users & Access" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Users &amp; Access</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Access roles decide what a user may do; timekeeper grades price their time.
                    Walled clients are managed on each client's page.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-2.5">User</th>
                            <th class="px-4 py-2.5">2FA</th>
                            <th class="px-4 py-2.5">Timekeeper grade</th>
                            <th class="px-4 py-2.5">Access role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="user in users" :key="user.id" :data-testid="`user-row-${user.id}`">
                            <td class="px-4 py-2.5">
                                <span class="font-medium text-gray-800">{{ user.name }}</span>
                                <div class="text-xs text-gray-400">{{ user.email }}</div>
                            </td>
                            <td class="px-4 py-2.5">
                                <Tag
                                    :value="user.two_factor ? 'Enabled' : 'Off'"
                                    :severity="user.two_factor ? 'success' : 'secondary'"
                                    class="!text-xs"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <SelectInput
                                    :model-value="user.role"
                                    :options="grades"
                                    class="w-44"
                                    @update:model-value="(value) => update(user, 'role', value)"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <SelectInput
                                    :model-value="user.access_role"
                                    :options="accessRoles"
                                    class="w-44"
                                    @update:model-value="(value) => update(user, 'access_role', value)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-lg bg-white p-5 text-sm text-gray-600 shadow-sm">
                <h3 class="mb-2 font-semibold text-gray-800">What each role can do</h3>
                <ul class="space-y-1">
                    <li v-for="role in accessRoles" :key="role.value">
                        <span class="font-medium text-gray-800">{{ role.label }}</span>
                        — {{ role.description }}
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
