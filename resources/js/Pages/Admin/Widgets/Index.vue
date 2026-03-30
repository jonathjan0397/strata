<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ appUrl: String })

const widgets = [
    {
        key: 'catalog',
        name: 'Service Catalog',
        description: 'Displays your live product catalog with pricing. Visitors can click through to your portal to order.',
        snippet: (url) => `<div\n  data-strata-widget="catalog"\n  data-strata-url="${url}"\n  data-strata-limit="6"\n  data-strata-theme="glass"\n></div>\n<script src="${url}/strata-widget.js" async><\/script>`,
    },
    {
        key: 'announcements',
        name: 'Announcements',
        description: 'Shows your latest published announcements — ideal for a status or news section on your homepage.',
        snippet: (url) => `<div\n  data-strata-widget="announcements"\n  data-strata-url="${url}"\n></div>\n<script src="${url}/strata-widget.js" async><\/script>`,
    },
    {
        key: 'kb',
        name: 'Knowledge Base',
        description: 'Embeds your knowledge base articles grouped by category.',
        snippet: (url) => `<div\n  data-strata-widget="kb"\n  data-strata-url="${url}"\n></div>\n<script src="${url}/strata-widget.js" async><\/script>`,
    },
    {
        key: 'support',
        name: 'Support Button',
        description: 'A compact call-to-action that links visitors to your support portal.',
        snippet: (url) => `<div\n  data-strata-widget="support"\n  data-strata-url="${url}"\n></div>\n<script src="${url}/strata-widget.js" async><\/script>`,
    },
    {
        key: 'domain-search',
        name: 'Domain Search',
        description: 'Live domain availability search bar. Results link through to your portal checkout.',
        snippet: (url) => `<div\n  data-strata-widget="domain-search"\n  data-strata-url="${url}"\n></div>\n<script src="${url}/strata-widget.js" async><\/script>`,
    },
]

const copied = {}

function copy(key, url, snippetFn) {
    navigator.clipboard.writeText(snippetFn(url)).then(() => {
        copied[key] = true
        setTimeout(() => { copied[key] = false }, 2000)
    })
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900">Widget Snippets</h1>
      <p class="mt-1 text-sm text-gray-500">
        Copy a snippet and paste it anywhere on your external website. Each widget loads live data from your Strata installation at
        <code class="text-indigo-600 text-xs font-mono">{{ appUrl }}</code>.
      </p>
    </div>

    <div class="space-y-4">
      <div
        v-for="w in widgets"
        :key="w.key"
        class="bg-white rounded-xl border border-gray-200 p-5"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h2 class="text-sm font-semibold text-gray-900">{{ w.name }}</h2>
            <p class="mt-0.5 text-xs text-gray-500">{{ w.description }}</p>
          </div>
          <button
            @click="copy(w.key, appUrl, w.snippet)"
            class="shrink-0 px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
            :class="copied[w.key]
              ? 'bg-green-50 border-green-200 text-green-700'
              : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
          >
            {{ copied[w.key] ? 'Copied!' : 'Copy' }}
          </button>
        </div>

        <pre class="mt-3 rounded-lg bg-gray-50 border border-gray-100 px-4 py-3 text-xs text-gray-700 font-mono overflow-x-auto whitespace-pre">{{ w.snippet(appUrl) }}</pre>
      </div>
    </div>
  </div>
</template>
