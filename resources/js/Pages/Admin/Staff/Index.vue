<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    staff: Array,
    availablePermissions: Array,
});

const permLabel = (p) => p.replace('access.', '').charAt(0).toUpperCase() + p.replace('access.', '').slice(1);
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Staff Permission Groups</h1>
        </template>

        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Member</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            <th class="relative px-6 py-3"><span class="sr-only">Edit</span></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="member in staff" :key="member.id">
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ member.name }}</p>
                                <p class="text-xs text-gray-500">{{ member.email }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="perm in member.permissions"
                                        :key="perm"
                                        class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700"
                                    >
                                        {{ permLabel(perm) }}
                                    </span>
                                    <span v-if="!member.permissions.length" class="text-xs text-gray-400">No permissions</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ member.created_at }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('admin.staff.edit', member.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                    Edit
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!staff.length">
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No staff accounts found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
