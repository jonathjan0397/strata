<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ module: { type: Object, default: null } })

const form = useForm({
  name:         props.module?.name         ?? '',
  type:         props.module?.type         ?? 'cpanel',
  hostname:     props.module?.hostname     ?? '',
  port:         props.module?.port         ?? 2087,
  username:     props.module?.username     ?? '',
  api_token:    '',
  ssl:          props.module?.ssl          ?? true,
  active:       props.module?.active       ?? true,
  max_accounts: props.module?.max_accounts ?? '',
})

function submit() {
  if (props.module) {
    form.patch(route('admin.modules.update', props.module.id))
  } else {
    form.post(route('admin.modules.store'))
  }
}
</script>

<template>
  <div class="max-w-lg">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.modules.index')" class="text-sm text-gray-500 hover:text-gray-700">← Servers</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ module ? 'Edit Server' : 'Add Server' }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select v-model="form.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="t in ['cpanel','plesk','directadmin','vestacp','cyberpanel','generic']" :key="t" :value="t" class="uppercase">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hostname</label>
            <input v-model="form.hostname" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
            <input v-model="form.port" type="number" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
            <input v-model="form.username" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">API Token {{ module ? '(leave blank to keep)' : '' }}</label>
            <input v-model="form.api_token" type="password" :required="!module" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Accounts (blank = unlimited)</label>
            <input v-model="form.max_accounts" type="number" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="flex items-end gap-6 pb-1">
            <label class="flex items-center gap-2 text-sm"><input v-model="form.ssl" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" /> SSL</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" /> Active</label>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <Link :href="route('admin.modules.index')" class="text-sm text-gray-500 px-4 py-2">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {{ module ? 'Save Changes' : 'Add Server' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
