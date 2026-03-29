<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue'
import DomainSearch from '@/Components/DomainSearch.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: PortalLayout })

const props = defineProps({
  products:             Array,
  announcements:        Array,
  categories:           Array,
  siteName:             String,
  tagline:              String,
  registrarConfigured:  Boolean,
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
  <!-- Hero -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-20 pb-16 text-center">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium text-sky-300 mb-6"
      style="background: rgba(56,189,248,0.15); border: 1px solid rgba(56,189,248,0.3);">
      <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span>
      Hosting &amp; Services Platform
    </div>
    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-tight mb-6">
      Professional Hosting<br class="hidden sm:block">
      <span style="background: linear-gradient(135deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
        Made Simple
      </span>
    </h1>
    <p class="text-lg text-white/60 max-w-xl mx-auto mb-10">
      {{ tagline }}
    </p>
    <div class="flex flex-wrap justify-center gap-4">
      <Link :href="route('portal.products')"
        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-white transition-all"
        style="background: linear-gradient(135deg, #0ea5e9, #6366f1); box-shadow: 0 4px 20px rgba(14,165,233,0.4);">
        View Services
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
        </svg>
      </Link>
      <Link :href="route('login')"
        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-medium text-white/80 transition-all"
        style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px);"
        onmouseover="this.style.background='rgba(255,255,255,0.14)'"
        onmouseout="this.style.background='rgba(255,255,255,0.08)'">
        Client Login
      </Link>
    </div>

    <!-- Stats row -->
    <div class="mt-16 grid grid-cols-3 gap-6 max-w-md mx-auto">
      <div v-for="s in [{n:'99.9%',l:'Uptime'},{n:'24/7',l:'Support'},{n:'SSL',l:'Included'}]" :key="s.l"
        class="text-center">
        <div class="text-2xl font-bold text-white">{{ s.n }}</div>
        <div class="text-xs text-white/50 mt-1">{{ s.l }}</div>
      </div>
    </div>
  </section>

  <!-- Domain Search -->
  <section v-if="registrarConfigured" class="max-w-3xl mx-auto px-4 sm:px-6 pb-16">
    <div class="rounded-2xl p-8 text-center"
      style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(12px);">
      <h2 class="text-xl font-bold text-white mb-2">Find Your Domain</h2>
      <p class="text-sm text-white/55 mb-6">Search for the perfect domain name for your website.</p>
      <DomainSearch />
    </div>
  </section>

  <!-- Products teaser -->
  <section v-if="products?.length" class="max-w-7xl mx-auto px-4 sm:px-6 pb-20">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-white">Popular Plans</h2>
      <Link :href="route('portal.products')" class="text-sm text-sky-400 hover:text-sky-300 transition-colors">
        View all →
      </Link>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="p in products" :key="p.id"
        class="flex flex-col rounded-2xl p-6 transition-all duration-200 group"
        style="background: rgba(255,255,255,0.07); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.12);"
        onmouseover="this.style.background='rgba(255,255,255,0.12)';this.style.borderColor='rgba(255,255,255,0.22)'"
        onmouseout="this.style.background='rgba(255,255,255,0.07)';this.style.borderColor='rgba(255,255,255,0.12)'">
        <div class="flex items-start justify-between mb-3">
          <h3 class="font-semibold text-white text-base leading-snug">{{ p.name }}</h3>
          <span class="text-xs font-medium px-2 py-0.5 rounded-full capitalize shrink-0 ml-2 border"
            :class="typeColor[p.type] ?? typeColor.other">
            {{ p.type }}
          </span>
        </div>
        <p v-if="p.description" class="text-sm text-white/55 mb-4 flex-1 leading-relaxed">{{ p.description }}</p>
        <div class="flex-1" v-else />
        <div class="mt-auto">
          <div class="mb-4">
            <span class="text-3xl font-bold text-white">${{ p.price }}</span>
            <span class="text-sm text-white/50">{{ cycleLabel[p.billing_cycle] ?? '' }}</span>
            <div v-if="Number(p.setup_fee) > 0" class="text-xs text-white/40 mt-1">${{ p.setup_fee }} setup fee</div>
          </div>
          <Link :href="route('register')"
            class="block w-full text-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all"
            style="background: linear-gradient(135deg, #0ea5e9, #6366f1);"
            onmouseover="this.style.opacity='0.85'"
            onmouseout="this.style.opacity='1'">
            Order Now
          </Link>
        </div>
      </div>
    </div>
  </section>

  <!-- Feature highlights -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 pb-20">
    <div class="rounded-2xl p-8 sm:p-12 grid grid-cols-1 md:grid-cols-3 gap-8"
      style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px);">
      <div v-for="f in [
        { icon: '🛡️', title: 'Secure & Reliable',   desc: 'Enterprise-grade infrastructure with 99.9% uptime SLA and proactive monitoring.' },
        { icon: '⚡', title: 'Instant Provisioning', desc: 'Services are provisioned automatically the moment your order is confirmed.' },
        { icon: '💬', title: 'Real Support',          desc: 'Talk to humans, not bots. Our team responds fast via your support portal.' },
      ]" :key="f.title" class="text-center sm:text-left">
        <div class="text-3xl mb-3">{{ f.icon }}</div>
        <h3 class="font-semibold text-white mb-2">{{ f.title }}</h3>
        <p class="text-sm text-white/55 leading-relaxed">{{ f.desc }}</p>
      </div>
    </div>
  </section>

  <!-- Announcements teaser -->
  <section v-if="announcements?.length" class="max-w-7xl mx-auto px-4 sm:px-6 pb-20">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-white">Latest News</h2>
      <Link :href="route('portal.announcements')" class="text-sm text-sky-400 hover:text-sky-300 transition-colors">All announcements →</Link>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <div v-for="a in announcements" :key="a.id"
        class="rounded-2xl p-5"
        style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px);">
        <div class="text-xs text-sky-400/70 mb-2">
          {{ a.published_at ? new Date(a.published_at).toLocaleDateString(undefined, { year:'numeric', month:'short', day:'numeric' }) : '' }}
        </div>
        <h3 class="font-semibold text-white mb-2 leading-snug">{{ a.title }}</h3>
        <p class="text-sm text-white/55 line-clamp-3 leading-relaxed">{{ a.body }}</p>
      </div>
    </div>
  </section>

  <!-- Knowledge base teaser -->
  <section v-if="categories?.length" class="max-w-7xl mx-auto px-4 sm:px-6 pb-20">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-white">Help Center</h2>
      <Link :href="route('portal.kb')" class="text-sm text-sky-400 hover:text-sky-300 transition-colors">Browse all →</Link>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div v-for="cat in categories" :key="cat.id"
        class="rounded-2xl p-5"
        style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px);">
        <h3 class="font-semibold text-sky-300 mb-3 text-sm uppercase tracking-wider">{{ cat.name }}</h3>
        <ul class="space-y-1.5">
          <li v-for="art in cat.published_articles" :key="art.id">
            <Link :href="route('portal.kb.show', art.slug)"
              class="text-sm text-white/70 hover:text-white transition-colors hover:underline underline-offset-2">
              {{ art.title }}
            </Link>
          </li>
        </ul>
        <Link :href="route('portal.kb')" class="mt-4 inline-block text-xs text-sky-400/70 hover:text-sky-300 transition-colors">
          More articles →
        </Link>
      </div>
    </div>
  </section>

  <!-- CTA banner -->
  <section class="max-w-7xl mx-auto px-4 sm:px-6 pb-20">
    <div class="rounded-2xl p-8 sm:p-12 text-center relative overflow-hidden"
      style="background: linear-gradient(135deg, rgba(14,165,233,0.25) 0%, rgba(99,102,241,0.25) 100%); border: 1px solid rgba(255,255,255,0.15); backdrop-filter: blur(12px);">
      <div class="absolute inset-0 opacity-10"
        style="background: radial-gradient(circle at 50% 50%, #38bdf8 0%, transparent 70%);"/>
      <h2 class="text-2xl sm:text-3xl font-bold text-white mb-4 relative">Ready to get started?</h2>
      <p class="text-white/60 mb-8 relative">Create your account and order a service in under 2 minutes.</p>
      <Link :href="route('register')"
        class="inline-flex items-center gap-2 px-8 py-3 rounded-xl text-sm font-semibold text-white relative"
        style="background: linear-gradient(135deg, #0ea5e9, #6366f1); box-shadow: 0 4px 20px rgba(14,165,233,0.4);">
        Create Free Account
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
        </svg>
      </Link>
    </div>
  </section>
</template>
