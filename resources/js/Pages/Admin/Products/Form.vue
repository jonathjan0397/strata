<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ product: { type: Object, default: null } })

const form = useForm({
  name:          props.product?.name          ?? '',
  description:   props.product?.description   ?? '',
  type:          props.product?.type          ?? 'shared',
  price:         props.product?.price         ?? '0.00',
  setup_fee:     props.product?.setup_fee     ?? '0.00',
  billing_cycle: props.product?.billing_cycle ?? 'monthly',
  module:        props.product?.module        ?? '',
  hidden:        props.product?.hidden        ?? false,
  taxable:       props.product?.taxable       ?? true,
  sort_order:    props.product?.sort_order    ?? 0,
})

function submit() {
  if (props.product) {
    form.patch(route('admin.products.update', props.product.id))
  } else {
    form.post(route('admin.products.store'))
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.products.index')" class="text-sm text-gray-500 hover:text-gray-700">← Products</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ product ? 'Edit Product' : 'New Product' }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select v-model="form.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="t in ['shared','reseller','vps','dedicated','domain','ssl','other']" :key="t" :value="t" class="capitalize">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
            <select v-model="form.billing_cycle" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="c in ['monthly','quarterly','semi_annual','annual','biennial','triennial','one_time']" :key="c" :value="c">{{ c.replace(/_/g,' ') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Price ($)</label>
            <input v-model="form.price" type="number" step="0.01" min="0" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Setup Fee ($)</label>
            <input v-model="form.setup_fee" type="number" step="0.01" min="0" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="col-span-2 flex items-center gap-6">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.hidden" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
              Hidden from order form
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.taxable" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
              Taxable
            </label>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <Link :href="route('admin.products.index')" class="text-sm text-gray-500 px-4 py-2">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {{ product ? 'Save Changes' : 'Create Product' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
