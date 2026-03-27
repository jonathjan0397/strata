<script setup>
const props = defineProps({ status: String })

const map = {
  active:          'bg-green-100 text-green-800',
  paid:            'bg-green-100 text-green-800',
  completed:       'bg-green-100 text-green-800',
  answered:        'bg-blue-100 text-blue-800',
  open:            'bg-indigo-100 text-indigo-800',
  customer_reply:  'bg-purple-100 text-purple-800',
  unpaid:          'bg-yellow-100 text-yellow-800',
  overdue:         'bg-red-100 text-red-800',
  pending:         'bg-gray-100 text-gray-700',
  suspended:       'bg-orange-100 text-orange-800',
  cancelled:       'bg-gray-100 text-gray-500',
  terminated:      'bg-red-100 text-red-700',
  on_hold:         'bg-yellow-100 text-yellow-700',
  closed:          'bg-gray-100 text-gray-500',
  draft:           'bg-gray-100 text-gray-500',
  refunded:        'bg-teal-100 text-teal-700',
  fraud:           'bg-red-100 text-red-800',
  expired:         'bg-red-100 text-red-700',
  transferred:     'bg-gray-100 text-gray-500',
}

const cls = props.status ? (map[props.status] ?? 'bg-gray-100 text-gray-600') : ''
const label = props.status?.replace(/_/g, ' ') ?? ''
</script>

<template>
  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', cls]">
    {{ label }}
  </span>
</template>
