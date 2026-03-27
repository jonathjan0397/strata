<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    groups: Array,
});

const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    description: '',
    discount_type: 'none',
    discount_value: 0,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(group) {
    editingId.value = group.id;
    form.name = group.name;
    form.description = group.description ?? '';
    form.discount_type = group.discount_type;
    form.discount_value = group.discount_value;
    showForm.value = true;
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.client-groups.update', editingId.value), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    } else {
        form.post(route('admin.client-groups.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function destroy(id) {
    if (confirm('Delete this group? Clients will be unassigned.')) {
        form.delete(route('admin.client-groups.destroy', id));
    }
}

const discountLabel = (g) => {
    if (g.discount_type === 'percent') return `${g.discount_value}% off`;
    if (g.discount_type === 'fixed') return `$${g.discount_value} off`;
    return 'No discount';
};
</script>

<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold text-gray-900">Client Groups</h1>
                <button @click="openCreate" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    + New Group
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <!-- Form -->
            <div v-if="showForm" class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">{{ editingId ? 'Edit Group' : 'New Group' }}</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="form.name" type="text" class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                        <input v-model="form.description" type="text" class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Discount Type</label>
                        <select v-model="form.discount_type" class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="none">None</option>
                            <option value="percent">Percent (%)</option>
                            <option value="fixed">Fixed ($)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Discount Value</label>
                        <input v-model="form.discount_value" type="number" min="0" step="0.01"
                            :disabled="form.discount_type === 'none'"
                            class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400" />
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button @click="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : (editingId ? 'Update' : 'Create') }}
                    </button>
                    <button @click="showForm = false" class="text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-left">Discount</th>
                            <th class="px-4 py-3 text-left">Clients</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="group in groups" :key="group.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ group.name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ group.description ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ discountLabel(group) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ group.users_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="openEdit(group)" class="text-xs text-indigo-600 hover:underline mr-3">Edit</button>
                                <button @click="destroy(group.id)" class="text-xs text-red-500 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!groups.length">
                            <td colspan="5" class="py-10 text-center text-sm text-gray-400">No groups yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
