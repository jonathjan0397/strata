<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  team:                 Array,
  availablePermissions: Array,
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const roleColors = {
  'super-admin': 'bg-purple-100 text-purple-700 ring-1 ring-purple-200',
  'admin':       'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
  'staff':       'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
}
const roleLabels = { 'super-admin': 'Super Admin', 'admin': 'Admin', 'staff': 'Staff' }

const permLabel = (p) => p.replace('access.', '').charAt(0).toUpperCase() + p.replace('access.', '').slice(1)

function destroy(member) {
  if (confirm(`Remove ${member.name} from the team? This cannot be undone.`)) {
    router.delete(route('admin.staff.destroy', member.id))
  }
}
</script>

<template>
  <div class="max-w-5xl">
    <!-- Flash -->
    <div v-if="flash?.success" class="mb-4 rounded-xl bg-green-50/80 border border-green-200/60 px-4 py-3 text-sm text-green-800">{{ flash.success }}</div>
    <div v-if="flash?.error"   class="mb-4 rounded-xl bg-red-50/80 border border-red-200/60 px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>

    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-slate-800">Team Members</h1>
      <Link :href="route('admin.staff.create')"
        class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-4 py-2 rounded-lg shadow-sm transition-colors">
        + Add Member
      </Link>
    </div>

    <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 shadow-sm overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead>
          <tr class="bg-slate-50/70">
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Member</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Role</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Permissions</th>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Joined</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="m in team" :key="m.id" class="hover:bg-blue-50/30 transition-colors">
            <td class="px-5 py-3.5">
              <p class="text-sm font-medium text-slate-800">{{ m.name }}</p>
              <p class="text-xs text-slate-400">{{ m.email }}</p>
            </td>
            <td class="px-5 py-3.5">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="roleColors[m.role] ?? roleColors.staff">
                {{ roleLabels[m.role] ?? m.role }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex flex-wrap gap-1">
                <span v-for="p in m.permissions" :key="p"
                  class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700">
                  {{ permLabel(p) }}
                </span>
                <span v-if="m.role !== 'staff' || !m.permissions.length" class="text-xs text-slate-400">
                  {{ m.role !== 'staff' ? 'Full access' : 'No permissions' }}
                </span>
              </div>
            </td>
            <td class="px-5 py-3.5 text-sm text-slate-400 whitespace-nowrap">{{ m.created_at }}</td>
            <td class="px-5 py-3.5 text-right whitespace-nowrap">
              <Link :href="route('admin.staff.edit', m.id)" class="text-sm text-blue-600 hover:text-blue-800 font-medium mr-4">Edit</Link>
              <button @click="destroy(m)" class="text-sm text-red-400 hover:text-red-600">Remove</button>
            </td>
          </tr>
          <tr v-if="!team.length">
            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No team members found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
