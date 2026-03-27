<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    departments: Array,
})

const form = useForm({
    subject:       '',
    department_id: props.departments?.[0]?.id ?? null,
    priority:      'medium',
    message:       '',
})

function submit() {
    form.post(route('client.support.store'))
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('client.support.index')" class="text-sm text-gray-500 hover:text-gray-700">← Support</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900">New Ticket</h1>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input v-model="form.subject" type="text" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.subject }" />
                    <p v-if="form.errors.subject" class="text-red-500 text-xs mt-1">{{ form.errors.subject }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select v-model="form.department_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select v-model="form.priority"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea v-model="form.message" rows="6" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        :class="{ 'border-red-400': form.errors.message }" />
                    <p v-if="form.errors.message" class="text-red-500 text-xs mt-1">{{ form.errors.message }}</p>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="route('client.support.index')" class="text-sm text-gray-500 px-4 py-2">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
