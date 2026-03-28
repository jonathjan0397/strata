<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
  availablePermissions: Array,
})

const form = useForm({
  name:                  '',
  email:                 '',
  password:              '',
  password_confirmation: '',
  role:                  'staff',
  permissions:           [],
})

const permLabel = (p) => p.replace('access.', '').charAt(0).toUpperCase() + p.replace('access.', '').slice(1)

const permDescriptions = {
  'access.billing':   'View and manage invoices, payments, and financial records',
  'access.support':   'View and reply to support tickets',
  'access.technical': 'Manage services, modules, and server provisioning',
  'access.clients':   'View and manage client accounts',
  'access.reports':   'Access reporting and analytics',
}

function submit() {
  form.post(route('admin.staff.store'))
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.staff.index')" class="text-sm text-slate-400 hover:text-slate-600">← Team</Link>
      <span class="text-slate-200">/</span>
      <h1 class="text-xl font-bold text-slate-800">Add Team Member</h1>
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Account Info -->
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Account Info</h2>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Full Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" required
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <p v-if="form.errors.name" class="text-red-500 text-xs mt-0.5">{{ form.errors.name }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email Address <span class="text-red-500">*</span></label>
          <input v-model="form.email" type="email" required
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <p v-if="form.errors.email" class="text-red-500 text-xs mt-0.5">{{ form.errors.email }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Role <span class="text-red-500">*</span></label>
          <select v-model="form.role"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="admin">Admin — full access, no individual permissions needed</option>
            <option value="staff">Staff — limited access, set permissions below</option>
          </select>
        </div>
      </div>

      <!-- Password -->
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Password</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password <span class="text-red-500">*</span></label>
            <input v-model="form.password" type="password" autocomplete="new-password" required
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Min. 8 characters" />
            <p v-if="form.errors.password" class="text-red-500 text-xs mt-0.5">{{ form.errors.password }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
            <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="••••••••" />
          </div>
        </div>
      </div>

      <!-- Permissions (staff only) -->
      <div v-if="form.role === 'staff'" class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm space-y-3">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Permissions</h2>
        <p class="text-xs text-slate-400">Controls which admin sections this staff member can access.</p>
        <div class="divide-y divide-slate-100 rounded-lg border border-slate-200 overflow-hidden">
          <label v-for="perm in availablePermissions" :key="perm"
            class="flex items-start gap-4 px-4 py-3.5 cursor-pointer hover:bg-slate-50/60 transition-colors">
            <input type="checkbox" :value="perm" v-model="form.permissions"
              class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
            <div>
              <p class="text-sm font-medium text-slate-700">{{ permLabel(perm) }}</p>
              <p class="text-xs text-slate-400 mt-0.5">{{ permDescriptions[perm] }}</p>
            </div>
          </label>
        </div>
      </div>
      <div v-else class="bg-slate-50/60 rounded-xl border border-slate-200/60 px-5 py-4 text-sm text-slate-500">
        <span class="font-medium">Admins</span> have full access to all sections — individual permissions do not apply.
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Link :href="route('admin.staff.index')" class="text-sm text-slate-500 px-4 py-2 hover:text-slate-700">Cancel</Link>
        <button type="submit" :disabled="form.processing"
          class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg shadow-sm transition-colors">
          {{ form.processing ? 'Adding…' : 'Add Team Member' }}
        </button>
      </div>

    </form>
  </div>
</template>
