<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    rates: Array,
});

const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    rate: '',
    country: '',
    state: '',
    is_default: false,
    active: true,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.active = true;
    showForm.value = true;
}

function openEdit(rate) {
    editingId.value = rate.id;
    form.name = rate.name;
    form.rate = rate.rate;
    form.country = rate.country ?? '';
    form.state = rate.state ?? '';
    form.is_default = rate.is_default;
    form.active = rate.active;
    showForm.value = true;
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.tax-rates.update', editingId.value), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    } else {
        form.post(route('admin.tax-rates.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function destroy(id) {
    if (confirm('Delete this tax rate?')) {
        form.delete(route('admin.tax-rates.destroy', id));
    }
}
</script>

<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold text-gray-900">Tax Rates</h1>
                <button @click="openCreate" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    + New Rate
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <!-- Form -->
            <div v-if="showForm" class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">{{ editingId ? 'Edit Tax Rate' : 'New Tax Rate' }}</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="form.name" type="text" placeholder="e.g. EU VAT, US Sales Tax"
                            class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                        <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Rate (%)</label>
                        <input v-model="form.rate" type="number" min="0" max="100" step="0.01" placeholder="20.00"
                            class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Country (ISO 2) <span class="text-gray-400">optional</span></label>
                        <input v-model="form.country" type="text" maxlength="2" placeholder="US, GB, DE…"
                            class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm uppercase focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">State/Province <span class="text-gray-400">optional</span></label>
                        <input v-model="form.state" type="text" maxlength="10" placeholder="CA, TX, NY…"
                            class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm uppercase focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div class="flex items-end gap-4 pb-0.5">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300" />
                            Default rate
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input v-model="form.active" type="checkbox" class="rounded border-gray-300" />
                            Active
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button @click="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : (editingId ? 'Update' : 'Create') }}
                    </button>
                    <button @click="showForm = false" class="text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                </div>
            </div>

            <p class="text-xs text-gray-500">Tax is applied at checkout when a product is marked taxable and the client is not tax-exempt. Country/state rules take priority over the default rate.</p>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Rate</th>
                            <th class="px-4 py-3 text-left">Country</th>
                            <th class="px-4 py-3 text-left">State</th>
                            <th class="px-4 py-3 text-left">Default</th>
                            <th class="px-4 py-3 text-left">Active</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="rate in rates" :key="rate.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ rate.name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ rate.rate }}%</td>
                            <td class="px-4 py-3 text-gray-500">{{ rate.country ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ rate.state ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="rate.is_default" class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Default</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="rate.active ? 'text-green-600' : 'text-gray-400'" class="text-xs font-medium">
                                    {{ rate.active ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button @click="openEdit(rate)" class="text-xs text-indigo-600 hover:underline mr-3">Edit</button>
                                <button @click="destroy(rate.id)" class="text-xs text-red-500 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!rates.length">
                            <td colspan="7" class="py-10 text-center text-sm text-gray-400">No tax rates configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
