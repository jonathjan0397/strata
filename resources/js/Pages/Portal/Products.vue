<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: PortalLayout })

const props = defineProps({ products: Array, siteName: String })

const activeFilter = ref('all')

const types = computed(() => {
  const t = new Set(props.products?.map(p => p.type) ?? [])
  return ['all', ...t]
})

const filtered = computed(() => {
  if (activeFilter.value === 'all') return props.products ?? []
  return (props.products ?? []).filter(p => p.type === activeFilter.value)
})

const cycleLabel = {
  monthly: '/mo', quarterly: '/qtr', semi_annual: '/6mo',
  annual: '/yr', biennial: '/2yr', triennial: '/3yr', one_time: ' one-time',
}

const typeColor = {
  shared:    'bg-blue-500/20 text-blue-200 border-blue-400/30',
  reseller:  'bg-purple-500/20 text-purple-200 border-purple-400/30',
  vps:       'bg-amber-500/20 text-amber-200 border-amber-400/30',
  dedicated: 'bg-red-500/20 text-red-200 border-red-400/30',
  domain:    'bg-emerald-500/20 text-emerald-200 border-emerald-400/30',
  ssl:       'bg-cyan-500/20 text-cyan-200 border-cyan-400/30',
  other:     'bg-white/10 text-white/60 border-white/20',
}
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">

    <!-- Page header -->
    <div class="text-center mb-12">
      <h1 class="text-3xl sm:text-4xl font-bold text-white mb-4">Services &amp; Plans</h1>
      <p class="text-white/55 max-w-xl mx-auto">Choose from our range of hosting products and services. All plans include 24/7 support and a 99.9% uptime guarantee.</p>
    </div>

    <!-- Type filter pills -->
    <div v-if="types.length > 2" class="flex flex-wrap justify-center gap-2 mb-10">
      <button
        v-for="t in types" :key="t"
        @click="activeFilter = t"
        class="px-4 py-1.5 rounded-full text-sm font-medium capitalize transition-all"
        :style="activeFilter === t
          ? 'background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff; box-shadow: 0 2px 10px rgba(14,165,233,0.3);'
          : 'background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.65); border: 1px solid rgba(255,255,255,0.15);'">
        {{ t === 'all' ? 'All Plans' : t }}
      </button>
    </div>

    <!-- Product grid -->
    <div v-if="filtered.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="p in filtered" :key="p.id"
        class="flex flex-col rounded-2xl p-6 transition-all duration-200"
        style="background: rgba(255,255,255,0.08); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.13);"
        onmouseover="this.style.background='rgba(255,255,255,0.13)';this.style.borderColor='rgba(255,255,255,0.24)';this.style.transform='translateY(-2px)'"
        onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.borderColor='rgba(255,255,255,0.13)';this.style.transform='translateY(0)'">

        <!-- Badge + name -->
        <div class="flex items-start justify-between mb-3">
          <h2 class="font-bold text-white text-lg leading-snug">{{ p.name }}</h2>
          <span class="shrink-0 ml-2 text-xs font-medium px-2.5 py-1 rounded-full capitalize border"
            :class="typeColor[p.type] ?? typeColor.other">
            {{ p.type }}
          </span>
        </div>

        <!-- Description -->
        <p v-if="p.description" class="text-sm text-white/55 leading-relaxed flex-1 mb-5">{{ p.description }}</p>
        <div v-else class="flex-1" />

        <!-- Pricing -->
        <div class="mt-auto">
          <div class="mb-4 pb-4"
            style="border-bottom: 1px solid rgba(255,255,255,0.1);">
            <div>
              <span class="text-3xl font-bold text-white">${{ p.price }}</span>
              <span class="text-sm text-white/50 ml-1">{{ cycleLabel[p.billing_cycle] ?? '' }}</span>
            </div>
            <div v-if="Number(p.setup_fee) > 0" class="text-xs text-white/40 mt-1">
              + ${{ p.setup_fee }} one-time setup fee
            </div>
          </div>

          <!-- Features hint -->
          <ul class="space-y-1.5 mb-5 text-sm text-white/60">
            <li class="flex items-center gap-2">
              <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              {{ cycleLabel[p.billing_cycle] ? 'Recurring ' + cycleLabel[p.billing_cycle].replace('/','') + ' billing' : 'One-time payment' }}
            </li>
            <li class="flex items-center gap-2">
              <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              24/7 support included
            </li>
            <li class="flex items-center gap-2">
              <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              99.9% uptime guarantee
            </li>
          </ul>

          <Link :href="route('register')"
            class="block w-full text-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all"
            style="background: linear-gradient(135deg, #0ea5e9, #6366f1); box-shadow: 0 2px 12px rgba(14,165,233,0.3);"
            onmouseover="this.style.boxShadow='0 4px 20px rgba(14,165,233,0.5)'"
            onmouseout="this.style.boxShadow='0 2px 12px rgba(14,165,233,0.3)'">
            Order Now
          </Link>
          <p class="text-center text-xs text-white/30 mt-2">Sign in or create an account to order</p>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-20 text-white/40">No products in this category.</div>

    <!-- Widget embed instructions -->
    <div class="mt-20 rounded-2xl p-8"
      style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px);">
      <h2 class="text-lg font-bold text-white mb-2">Embed this catalog on your website</h2>
      <p class="text-sm text-white/55 mb-5">Paste the following snippet anywhere on your existing site to show a live product listing.</p>
      <div class="rounded-xl p-4 font-mono text-xs text-sky-200 overflow-x-auto"
        style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08);">
        <div class="text-white/30 mb-1">&lt;!-- Strata Service Catalog Widget --&gt;</div>
        <div>&lt;div</div>
        <div class="pl-4">data-strata-widget=<span class="text-amber-300">"catalog"</span></div>
        <div class="pl-4">data-strata-url=<span class="text-amber-300">"{{ $page.props.ziggy?.url ?? '' }}"</span></div>
        <div class="pl-4">data-strata-limit=<span class="text-amber-300">"6"</span></div>
        <div class="pl-4">data-strata-theme=<span class="text-amber-300">"glass"</span><span class="text-white/40"> &lt;!-- or "light" --&gt;</span></div>
        <div>&gt;&lt;/div&gt;</div>
        <div class="mt-2">&lt;script src=<span class="text-amber-300">"{{ $page.props.ziggy?.url ?? '' }}/strata-widget.js"</span> async&gt;&lt;/script&gt;</div>
      </div>
    </div>
  </div>
</template>
