<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    staff: Object,
    availablePermissions: Array,
});

const form = useForm({
    permissions: [...(props.staff.permissions ?? [])],
});

const permLabel = (p) => p.replace('access.', '').charAt(0).toUpperCase() + p.replace('access.', '').slice(1);

const permDescriptions = {
    'access.billing':   'View and manage invoices, payments, and financial records',
    'access.support':   'View and reply to support tickets',
    'access.technical': 'Manage services, modules, and server provisioning',
    'access.clients':   'View and manage client accounts',
    'access.reports':   'Access reporting and analytics (future)',
};

function submit() {
    form.patch(route('admin.staff.update', props.staff.id));
}
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Edit Permissions — {{ staff.name }}</h1>
        </template>

        <div class="max-w-2xl mx-auto">
            <form @submit.prevent="submit" class="space-y-4">
                <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
                    <label
                        v-for="perm in availablePermissions"
                        :key="perm"
                        class="flex items-start gap-4 px-6 py-4 cursor-pointer hover:bg-gray-50"
                    >
                        <input
                            type="checkbox"
                            :value="perm"
                            v-model="form.permissions"
                            class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ permLabel(perm) }}</p>
                            <p class="text-xs text-gray-500">{{ permDescriptions[perm] }}</p>
                        </div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <a :href="route('admin.staff.index')" class="text-sm text-gray-600 hover:text-gray-900">← Back</a>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Save Permissions
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
