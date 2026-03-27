<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ template: Object })

const form = useForm({
  name:       props.template.name,
  subject:    props.template.subject,
  body_html:  props.template.body_html,
  body_plain: props.template.body_plain ?? '',
  active:     props.template.active,
})

const variableMap = {
  'auth.welcome':      ['name', 'app_name', 'login_url'],
  'invoice.created':   ['name', 'app_name', 'invoice_id', 'amount', 'due_date', 'invoice_url'],
  'invoice.paid':      ['name', 'app_name', 'invoice_id', 'amount', 'invoice_url'],
  'invoice.overdue':   ['name', 'app_name', 'invoice_id', 'amount', 'due_date', 'invoice_url'],
  'service.activated': ['name', 'app_name', 'service_name', 'domain', 'username', 'portal_url'],
  'service.suspended': ['name', 'app_name', 'service_name', 'domain', 'invoices_url'],
  'support.reply':     ['name', 'app_name', 'ticket_id', 'ticket_subject', 'reply_body', 'ticket_url'],
}

const vars = variableMap[props.template.slug] ?? []
</script>

<template>
  <div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.email-templates.index')" class="text-sm text-gray-500 hover:text-gray-700">← Email Templates</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ template.name }}</h1>
      <code class="ml-1 text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ template.slug }}</code>
    </div>

    <!-- Available variables -->
    <div v-if="vars.length" class="mb-5 bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3">
      <p class="text-xs font-semibold text-indigo-700 mb-2">Available Variables</p>
      <div class="flex flex-wrap gap-2">
        <code v-for="v in vars" :key="v" class="text-xs bg-white border border-indigo-200 text-indigo-600 px-2 py-0.5 rounded">&#123;&#123;{{ v }}&#125;&#125;</code>
      </div>
    </div>

    <form @submit.prevent="form.patch(route('admin.email-templates.update', template.id))" class="space-y-5">

      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <div class="flex items-center gap-3">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="flex items-center gap-2 pt-5">
            <input id="active" v-model="form.active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
            <label for="active" class="text-sm text-gray-700">Active</label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
          <input v-model="form.subject" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <p v-if="form.errors.subject" class="text-red-500 text-xs mt-1">{{ form.errors.subject }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Body (HTML)</label>
          <textarea v-model="form.body_html" rows="12" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <p class="text-xs text-gray-400 mt-1">HTML is wrapped in a branded layout automatically. Use <code>&lt;p&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;a class="btn"&gt;</code>.</p>
          <p v-if="form.errors.body_html" class="text-red-500 text-xs mt-1">{{ form.errors.body_html }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Plain Text (optional)</label>
          <textarea v-model="form.body_plain" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <p class="text-xs text-gray-400 mt-1">Falls back to stripped HTML if left blank.</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing"
          class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
          Save Template
        </button>
        <Link :href="route('admin.email-templates.index')" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
      </div>
    </form>
  </div>
</template>
