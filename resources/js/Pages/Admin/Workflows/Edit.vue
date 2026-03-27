<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    workflow: Object,
    triggers: Array,
    operators: Array,
    actionTypes: Array,
});

const isEdit = !!props.workflow;

const form = useForm({
    name:       props.workflow?.name ?? '',
    trigger:    props.workflow?.trigger ?? '',
    is_active:  props.workflow?.is_active ?? true,
    conditions: props.workflow?.conditions?.map(c => ({ ...c })) ?? [],
    actions:    props.workflow?.actions?.map(a => ({ ...a, config: a.config ?? {} })) ?? [],
});

// ---- Conditions ----
function addCondition() {
    form.conditions.push({ field: '', operator: 'eq', value: '', sort_order: form.conditions.length });
}
function removeCondition(i) { form.conditions.splice(i, 1); }

// ---- Actions ----
function addAction() {
    form.actions.push({ type: '', config: {}, delay_minutes: 0, sort_order: form.actions.length });
}
function removeAction(i) { form.actions.splice(i, 1); }

// Action config fields per type
const configFields = {
    'send.email':      [{ key: 'to', label: 'To (email or "client")', placeholder: 'client' }, { key: 'subject', label: 'Subject' }, { key: 'body', label: 'Body', multiline: true }],
    'create.ticket':   [{ key: 'subject', label: 'Subject' }, { key: 'department', label: 'Department', placeholder: 'general' }, { key: 'priority', label: 'Priority', placeholder: 'medium' }],
    'suspend.service': [],
    'add.credit':      [{ key: 'amount', label: 'Amount ($)', placeholder: '10.00' }, { key: 'description', label: 'Description' }],
    'call.webhook':    [{ key: 'url', label: 'Webhook URL' }],
};

const triggerLabel = (t) => t.replace('.', ': ').replace(/\b\w/g, c => c.toUpperCase());
const actionLabel  = (t) => t.replace('.', ' ').replace(/\b\w/g, c => c.toUpperCase());

function submit() {
    if (isEdit) {
        form.patch(route('admin.workflows.update', props.workflow.id));
    } else {
        form.post(route('admin.workflows.store'));
    }
}
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">{{ isEdit ? 'Edit Workflow' : 'New Workflow' }}</h1>
        </template>

        <div class="max-w-3xl mx-auto">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Basic info -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">1. Workflow</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="form.name" type="text" required placeholder="e.g. Send overdue reminder" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Trigger</label>
                            <select v-model="form.trigger" required class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select trigger…</option>
                                <option v-for="t in triggers" :key="t" :value="t">{{ triggerLabel(t) }}</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Active
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Conditions -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">2. Conditions <span class="text-xs font-normal text-gray-400">(ALL must pass)</span></h2>
                        <button type="button" @click="addCondition" class="text-xs text-indigo-600 hover:underline">+ Add condition</button>
                    </div>

                    <p v-if="!form.conditions.length" class="text-xs text-gray-400 italic">No conditions — workflow runs on every trigger event.</p>

                    <div v-for="(cond, i) in form.conditions" :key="i" class="flex gap-2 items-start">
                        <input v-model="cond.field" placeholder="field (e.g. invoice.total)" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <select v-model="cond.operator" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="op in operators" :key="op" :value="op">{{ op }}</option>
                        </select>
                        <input v-model="cond.value" placeholder="value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <button type="button" @click="removeCondition(i)" class="text-gray-400 hover:text-red-500 px-1 text-lg leading-none">×</button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">3. Actions <span class="text-xs font-normal text-gray-400">(executed in order)</span></h2>
                        <button type="button" @click="addAction" class="text-xs text-indigo-600 hover:underline">+ Add action</button>
                    </div>

                    <p v-if="!form.actions.length" class="text-xs text-gray-400 italic">No actions added yet.</p>

                    <div v-for="(action, i) in form.actions" :key="i" class="rounded-md border border-gray-200 p-4 space-y-3 relative">
                        <button type="button" @click="removeAction(i)" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-lg leading-none">×</button>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Action type</label>
                                <select v-model="action.type" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select…</option>
                                    <option v-for="t in actionTypes" :key="t" :value="t">{{ actionLabel(t) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Delay (minutes)</label>
                                <input v-model.number="action.delay_minutes" type="number" min="0" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <!-- Dynamic config fields -->
                        <template v-if="action.type && configFields[action.type]">
                            <div v-for="field in configFields[action.type]" :key="field.key">
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ field.label }}</label>
                                <textarea
                                    v-if="field.multiline"
                                    v-model="action.config[field.key]"
                                    rows="3"
                                    :placeholder="field.placeholder"
                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <input
                                    v-else
                                    v-model="action.config[field.key]"
                                    type="text"
                                    :placeholder="field.placeholder"
                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-between">
                    <a :href="route('admin.workflows.index')" class="text-sm text-gray-600 hover:text-gray-900">← Back</a>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ isEdit ? 'Update Workflow' : 'Create Workflow' }}
                    </button>
                </div>

                <p v-if="Object.keys(form.errors).length" class="text-xs text-red-600">
                    Please fix the errors above before saving.
                </p>
            </form>
        </div>
    </AppLayout>
</template>
