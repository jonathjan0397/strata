<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ addon: { type: Object, default: null } })

const form = useForm({
  name:          props.addon?.name          ?? '',
  description:   props.addon?.description   ?? '',
  price:         props.addon?.price         ?? '0.00',
  setup_fee:     props.addon?.setup_fee     ?? '0.00',
  billing_cycle: props.addon?.billing_cycle ?? 'monthly',
  is_active:     props.addon?.is_active     ?? true,
  sort_order:    props.addon?.sort_order    ?? 0,
})

const isEditing = !!props.addon

function submit() {
  if (isEditing) {
    form.patch(route('admin.addons.update', props.addon.id))
  } else {
    form.post(route('admin.addons.store'))
  }
}
</script>

<template>
  <div class="max-w-xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.addons.index')" class="text-sm text-gray-500 hover:text-gray-700">&larr; Addons</Link>
      <h1 class="text-xl font-bold text-gray-900">{{ isEditing ? 'Edit Addon' : 'New Addon' }}</h1>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" required />
        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
          <input v-model="form.price" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required />
          <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Setup Fee</label>
          <input v-model="form.setup_fee" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required />
          <p v-if="form.errors.setup_fee" class="mt-1 text-xs text-red-600">{{ form.errors.setup_fee }}</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
        <select v-model="form.billing_cycle" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="monthly">Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="semi_annual">Semi-Annual</option>
          <option value="annual">Annual</option>
          <option value="biennial">Biennial</option>
          <option value="triennial">Triennial</option>
          <option value="one_time">One-Time</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
          <input v-model.number="form.sort_order" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="flex items-end pb-2">
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
            Active
          </label>
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <Link :href="route('admin.addons.index')" class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2">Cancel</Link>
        <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
          {{ isEditing ? 'Save Changes' : 'Create Addon' }}
        </button>
      </div>
    </form>
  </div>
</template>
