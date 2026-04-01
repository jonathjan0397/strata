<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page   = usePage()
const user   = computed(() => page.props.auth?.user)
const flash  = computed(() => page.props.flash)
const siteName   = computed(() => page.props.siteName   ?? 'Strata Service Billing and Support Platform')
const logoUrl    = computed(() => page.props.logoUrl    ?? null)
const portalTheme = computed(() => page.props.portalTheme ?? 'blue')

const THEMES = {
  blue:      { bg: 'linear-gradient(135deg,#0c1445 0%,#1a3a6b 40%,#0e7490 80%,#0891b2 100%)', orb1:'#38bdf8', orb2:'#818cf8', orb3:'#0ea5e9', accent:'56,189,248', header:'12,20,69', btn1:'#0ea5e9', btn2:'#6366f1' },
  red:       { bg: 'linear-gradient(135deg,#2d0a0a 0%,#6b1a1a 40%,#991b1b 80%,#dc2626 100%)', orb1:'#f87171', orb2:'#fb923c', orb3:'#ef4444', accent:'248,113,113', header:'45,10,10', btn1:'#dc2626', btn2:'#b91c1c' },
  green:     { bg: 'linear-gradient(135deg,#042f1a 0%,#064e3b 40%,#065f46 80%,#059669 100%)', orb1:'#34d399', orb2:'#6ee7b7', orb3:'#10b981', accent:'52,211,153',  header:'4,47,26',   btn1:'#059669', btn2:'#047857' },
  lightblue: { bg: 'linear-gradient(135deg,#0a1628 0%,#1e3a5f 40%,#1e5799 80%,#3b82f6 100%)', orb1:'#93c5fd', orb2:'#a5b4fc', orb3:'#60a5fa', accent:'147,197,253', header:'10,22,40',  btn1:'#3b82f6', btn2:'#6366f1' },
}

const t = computed(() => THEMES[portalTheme.value] ?? THEMES.blue)

const mobileOpen = ref(false)

const nav = computed(() => [
  { label: 'Services',      href: route('portal.products') },
  { label: 'Announcements', href: route('portal.announcements') },
  { label: 'Help Center',   href: route('portal.kb') },
])
</script>

<template>
  <div class="min-h-screen relative overflow-x-hidden" :style="{ background: t.bg }">

    <!-- Decorative blurred orbs -->
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
      <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full opacity-20"
        :style="`background: radial-gradient(circle, ${t.orb1} 0%, transparent 70%); filter: blur(60px);`" />
      <div class="absolute top-1/3 -right-40 w-80 h-80 rounded-full opacity-15"
        :style="`background: radial-gradient(circle, ${t.orb2} 0%, transparent 70%); filter: blur(60px);`" />
      <div class="absolute bottom-0 left-1/3 w-72 h-72 rounded-full opacity-10"
        :style="`background: radial-gradient(circle, ${t.orb3} 0%, transparent 70%); filter: blur(80px);`" />
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-50"
      :style="`background: rgba(${t.header},0.55); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.1);`">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center gap-6">

        <!-- Logo -->
        <Link :href="route('home')" class="flex items-center gap-2 shrink-0">
          <template v-if="logoUrl">
            <img :src="logoUrl" :alt="siteName" class="h-8 w-auto object-contain" />
          </template>
          <template v-else>
            <div class="h-8 w-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
              :style="`background: linear-gradient(135deg, ${t.orb1}, ${t.btn2});`">
              {{ siteName.charAt(0).toUpperCase() }}
            </div>
            <span class="text-white font-semibold text-lg tracking-tight">{{ siteName }}</span>
          </template>
        </Link>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-1 ml-4">
          <a v-for="n in nav" :key="n.label" :href="n.href"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
            style="color: rgba(255,255,255,0.75);"
            onmouseover="this.style.background='rgba(255,255,255,0.08)';this.style.color='#fff'"
            onmouseout="this.style.background='transparent';this.style.color='rgba(255,255,255,0.75)'">
            {{ n.label }}
          </a>
        </nav>

        <!-- Right: CTA buttons -->
        <div class="ml-auto flex items-center gap-3">
          <template v-if="user">
            <Link :href="user.roles?.some(r => ['super-admin','admin','staff'].includes(r.name)) ? route('admin.dashboard') : route('client.dashboard')"
              class="hidden sm:inline-flex items-center px-4 py-1.5 rounded-lg text-sm font-medium text-white transition-colors"
              :style="`background: rgba(${t.accent},0.2); border: 1px solid rgba(${t.accent},0.35);`"
              @mouseover="$event.currentTarget.style.background=`rgba(${t.accent},0.35)`"
              @mouseout="$event.currentTarget.style.background=`rgba(${t.accent},0.2)`">
              My Portal
            </Link>
          </template>
          <template v-else>
            <Link :href="route('login')"
              class="hidden sm:inline-flex px-4 py-1.5 rounded-lg text-sm font-medium transition-colors"
              style="color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.2);"
              onmouseover="this.style.background='rgba(255,255,255,0.08)';this.style.color='#fff'"
              onmouseout="this.style.background='transparent';this.style.color='rgba(255,255,255,0.8)'">
              Sign In
            </Link>
            <Link :href="route('register')"
              class="inline-flex px-4 py-1.5 rounded-lg text-sm font-semibold text-white transition-all"
              :style="`background: linear-gradient(135deg, ${t.btn1}, ${t.btn2}); box-shadow: 0 2px 12px rgba(${t.accent},0.35);`"
              @mouseover="$event.currentTarget.style.boxShadow=`0 4px 20px rgba(${t.accent},0.5)`"
              @mouseout="$event.currentTarget.style.boxShadow=`0 2px 12px rgba(${t.accent},0.35)`">
              Get Started
            </Link>
          </template>

          <!-- Mobile hamburger -->
          <button class="md:hidden p-2 text-white/70 hover:text-white" @click="mobileOpen = !mobileOpen">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path v-if="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
              <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile nav drawer -->
      <div v-if="mobileOpen" class="md:hidden border-t px-4 py-3 space-y-1"
        :style="`border-color: rgba(255,255,255,0.1); background: rgba(${t.header},0.8);`">
        <a v-for="n in nav" :key="n.label" :href="n.href"
          class="block px-3 py-2 rounded-lg text-sm font-medium text-white/75 hover:text-white hover:bg-white/10">
          {{ n.label }}
        </a>
        <div class="pt-2 border-t border-white/10 flex gap-2">
          <Link :href="route('login')" class="flex-1 text-center py-2 rounded-lg text-sm text-white/80 border border-white/20 hover:bg-white/10">Sign In</Link>
          <Link :href="route('register')" class="flex-1 text-center py-2 rounded-lg text-sm text-white font-semibold"
            :style="`background: linear-gradient(135deg, ${t.btn1}, ${t.btn2});`">Get Started</Link>
        </div>
      </div>
    </header>

    <!-- Flash messages -->
    <div v-if="flash?.success || flash?.error" class="max-w-7xl mx-auto px-4 sm:px-6 pt-4 relative z-10">
      <div v-if="flash?.success"
        class="rounded-xl px-4 py-3 text-sm text-green-100"
        style="background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.35); backdrop-filter: blur(8px);">
        {{ flash.success }}
      </div>
      <div v-if="flash?.error"
        class="rounded-xl px-4 py-3 text-sm text-red-100"
        style="background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.35); backdrop-filter: blur(8px);">
        {{ flash.error }}
      </div>
    </div>

    <!-- Page slot -->
    <main class="relative z-10">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="relative z-10 mt-16"
      style="background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(8px);">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
          <div class="flex items-center gap-2">
            <template v-if="logoUrl">
              <img :src="logoUrl" :alt="siteName" class="h-6 w-auto object-contain opacity-70" />
            </template>
            <template v-else>
              <div class="h-6 w-6 rounded-md flex items-center justify-center text-white font-bold text-xs"
                :style="`background: linear-gradient(135deg, ${t.orb1}, ${t.btn2});`">
                {{ siteName.charAt(0).toUpperCase() }}
              </div>
              <span class="text-white/60 text-sm">{{ siteName }}</span>
            </template>
          </div>
          <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-white/50">
            <a :href="route('portal.products')"      class="hover:text-white/80 transition-colors">Services</a>
            <a :href="route('portal.kb')"            class="hover:text-white/80 transition-colors">Help Center</a>
            <a :href="route('portal.announcements')" class="hover:text-white/80 transition-colors">News</a>
            <a :href="route('login')"                class="hover:text-white/80 transition-colors">Client Login</a>
          </nav>
          <div class="text-right">
            <p class="text-white/30 text-xs">&copy; {{ new Date().getFullYear() }} {{ siteName }}. All rights reserved.</p>
            <p class="text-white/20 text-xs mt-0.5">Strata Service Billing and Support Platform &copy; 2026 Jonathan R. Covington</p>
            <a href="https://buymeacoffee.com/jonathan0397" target="_blank" rel="noopener noreferrer"
              class="text-white/20 text-xs mt-0.5 hover:text-white/40 transition-colors inline-block">☕ Buy me a coffee</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
