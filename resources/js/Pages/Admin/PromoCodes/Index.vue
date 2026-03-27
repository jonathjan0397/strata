<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

defineProps({ codes: Object })

const flash = computed(() => usePage().props.flash)

function destroy(id) {
  if (confirm('Delete this promo code?')) {
    router.delete(route('admin.promo-codes.destroy', id), { preserveScroll: true })
  }
}
</script>

<template>
  <div class="max-w-5xl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Promo Codes</h1>
      <Link :href="route('admin.promo-codes.create')"
        class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        + New Code
      </Link>
    </div>

    <div v-if="flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
      {{ flash.success }}
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full text-sm divide-y divide-gray-100">
        <thead class="bg-gray-50">
          <tr class="text-left text-gray-500 text-xs uppercase tracking-wide">
            <th class="px-4 py-3">Code</th>
            <th class="px-4 py-3">Discount</th>
            <th class="px-4 py-3">Product</th>
            <th class="px-4 py-3">Uses</th>
            <th class="px-4 py-3">Expires</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="c in codes.data" :key="c.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono font-semibold text-gray-900">{{ c.code }}</td>
            <td class="px-4 py-3">
              <span v-if="c.type === 'percent'">{{ c.value }}% off</span>
              <span v-else>${{ c.value }} off</span>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ c.product?.name ?? 'All products' }}</td>
            <td class="px-4 py-3 text-gray-500">
              {{ c.uses_count }}{{ c.max_uses ? ' / ' + c.max_uses : '' }}
            </td>
            <td class="px-4 py-3 text-gray-500">
              {{ c.expires_at ? new Date(c.expires_at).toLocaleDateString() : '—' }}
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                {{ c.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.promo-codes.edit', c.id)"
                class="text-indigo-600 hover:underline text-xs mr-3">Edit</Link>
              <button @click="destroy(c.id)" class="text-red-500 hover:underline text-xs">Delete</button>
            </td>
          </tr>
          <tr v-if="!codes.data?.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">No promo codes yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="codes.last_page > 1" class="mt-4 flex justify-end gap-2">
      <Link v-for="l in codes.links" :key="l.label" :href="l.url ?? '#'"
        v-html="l.label"
        class="px-3 py-1 text-sm rounded border"
        :class="l.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'" />
    </div>
  </div>
</template>
