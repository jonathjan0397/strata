<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    departments: Array,
    staff:       Array,
    clients:     Array,
})

const form = useForm({
    user_id:       '',
    subject:       '',
    department_id: '',
    priority:      'medium',
    message:       '',
    assigned_to:   '',
    internal:      false,
})

const clientSearch = ref('')
const showDropdown = ref(false)

const filteredClients = computed(() => {
    if (!clientSearch.value) return props.clients.slice(0, 20)
    const q = clientSearch.value.toLowerCase()
    return props.clients.filter(c =>
        c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q)
    ).slice(0, 20)
})

const selectedClient = computed(() =>
    props.clients.find(c => c.id === form.user_id) ?? null
)

function selectClient(client) {
    form.user_id = client.id
    clientSearch.value = ''
    showDropdown.value = false
}

function submit() {
    form.post(route('admin.support.store'))
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('admin.support.index')" class="text-sm text-gray-500 hover:text-gray-700">← Support</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900">New Ticket</h1>
        </div>

        <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

            <!-- Client -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                <div v-if="selectedClient" class="flex items-center justify-between px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50">
                    <span>{{ selectedClient.name }} <span class="text-gray-400">· {{ selectedClient.email }}</span></span>
                    <button type="button" @click="form.user_id = ''" class="text-gray-400 hover:text-red-500 ml-2">✕</button>
                </div>
                <div v-else class="relative">
                    <input
                        v-model="clientSearch"
                        type="text"
                        placeholder="Search by name or email…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.user_id }"
                        @focus="showDropdown = true"
                        @blur="setTimeout(() => showDropdown = false, 150)"
                    />
                    <div v-if="showDropdown && filteredClients.length" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto">
                        <button
                            v-for="c in filteredClients" :key="c.id"
                            type="button"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 flex justify-between"
                            @mousedown="selectClient(c)"
                        >
                            <span class="font-medium">{{ c.name }}</span>
                            <span class="text-gray-400 text-xs">{{ c.email }}</span>
                        </button>
                    </div>
                </div>
                <p v-if="form.errors.user_id" class="text-red-500 text-xs mt-1">{{ form.errors.user_id }}</p>
            </div>

            <!-- Subject -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                <input
                    v-model="form.subject"
                    type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :class="{ 'border-red-400': form.errors.subject }"
                />
                <p v-if="form.errors.subject" class="text-red-500 text-xs mt-1">{{ form.errors.subject }}</p>
            </div>

            <!-- Department + Priority -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select v-model="form.department_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select v-model="form.priority" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <!-- Assign to staff -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                <select v-model="form.assigned_to" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">— Unassigned —</option>
                    <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>

            <!-- Message -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Message <span class="text-red-500">*</span></label>
                <textarea
                    v-model="form.message"
                    rows="6"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                    :class="{ 'border-red-400': form.errors.message }"
                />
                <p v-if="form.errors.message" class="text-red-500 text-xs mt-1">{{ form.errors.message }}</p>
            </div>

            <!-- Internal toggle -->
            <div class="flex items-center gap-2">
                <input id="internal" v-model="form.internal" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                <label for="internal" class="text-sm text-gray-700">Mark as internal note (not visible to client)</label>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                >{{ form.processing ? 'Creating…' : 'Create Ticket' }}</button>
                <Link :href="route('admin.support.index')" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
            </div>
        </form>
    </div>
</template>
