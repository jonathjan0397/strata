<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  clients: Object,
  filters: Object,
})

const search     = ref(props.filters?.search      ?? '')
const status     = ref(props.filters?.status      ?? '')
const leadSource = ref(props.filters?.lead_source ?? '')

function applyFilters() {
  router.get(route('admin.clients.index'), {
    search:      search.value      || undefined,
    status:      status.value      || undefined,
    lead_source: leadSource.value  || undefined,
  }, { preserveState: true, replace: true })
}

watch([search, status, leadSource], applyFilters)

const statusColors = {
  prospect: 'bg-blue-100 text-blue-700',
  active:   'bg-green-100 text-green-700',
  inactive: 'bg-gray-100 text-gray-600',
  at_risk:  'bg-amber-100 text-amber-700',
  churned:  'bg-red-100 text-red-600',
}

function statusLabel(s) {
  return { prospect: 'Prospect', active: 'Active', inactive: 'Inactive', at_risk: 'At Risk', churned: 'Churned' }[s] ?? s
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Clients</h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ clients.total }} total</p>
      </div>
      <Link :href="route('admin.clients.create')"
        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium px-4 py-2 rounded-lg shadow-sm transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Add Client
      </Link>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-5">
      <input
        v-model="search"
        type="search"
        placeholder="Search name, email, company…"
        class="min-w-[220px] flex-1 border border-slate-200 bg-white/70 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"
      />
      <select v-model="status"
        class="border border-slate-200 bg-white/70 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
        <option value="">All Statuses</option>
        <option value="prospect">Prospect</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="at_risk">At Risk</option>
        <option value="churned">Churned</option>
      </select>
      <select v-model="leadSource"
        class="border border-slate-200 bg-white/70 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
        <option value="">All Sources</option>
        <option value="google">Google</option>
        <option value="referral">Referral</option>
        <option value="direct">Direct</option>
        <option value="social">Social Media</option>
        <option value="other">Other</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 overflow-hidden shadow-sm">
      <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50/80">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs">Client</th>
            <th class="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs">Contact</th>
            <th class="px-4 py-3 text-center font-semibold text-slate-500 uppercase tracking-wider text-xs">Status</th>
            <th class="px-4 py-3 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs">Source</th>
            <th class="px-4 py-3 text-center font-semibold text-slate-500 uppercase tracking-wider text-xs">Services</th>
            <th class="px-4 py-3 text-center font-semibold text-slate-500 uppercase tracking-wider text-xs">Invoices</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-500 uppercase tracking-wider text-xs">Joined</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="client in clients.data" :key="client.id"
            class="hover:bg-blue-50/30 transition-colors group">
            <td class="px-4 py-3">
              <Link :href="route('admin.clients.show', client.id)" class="font-semibold text-blue-700 hover:underline">
                {{ client.name }}
              </Link>
              <p v-if="client.company" class="text-xs text-slate-500 mt-0.5">{{ client.company }}</p>
            </td>
            <td class="px-4 py-3">
              <p class="text-slate-600">{{ client.email }}</p>
              <p v-if="client.phone" class="text-xs text-slate-400 mt-0.5">{{ client.phone }}</p>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="statusColors[client.client_status] ?? 'bg-gray-100 text-gray-600'">
                {{ statusLabel(client.client_status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500 text-xs capitalize">{{ client.lead_source ?? '—' }}</td>
            <td class="px-4 py-3 text-center text-slate-600 font-medium">{{ client.services_count }}</td>
            <td class="px-4 py-3 text-center text-slate-600 font-medium">{{ client.invoices_count }}</td>
            <td class="px-4 py-3 text-right text-slate-400 text-xs">
              {{ new Date(client.created_at).toLocaleDateString() }}
            </td>
          </tr>
          <tr v-if="!clients.data.length">
            <td colspan="7" class="px-4 py-10 text-center text-slate-400">No clients found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="clients.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-slate-500">
      <span>Showing {{ clients.from }}–{{ clients.to }} of {{ clients.total }}</span>
      <div class="flex gap-2">
        <Link v-if="clients.prev_page_url" :href="clients.prev_page_url"
          class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">← Prev</Link>
        <Link v-if="clients.next_page_url" :href="clients.next_page_url"
          class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Next →</Link>
      </div>
    </div>
  </div>
</template>
