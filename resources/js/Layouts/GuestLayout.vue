<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-950 px-4">
    <div class="mb-8">
      <img src="/logo.svg" alt="Strata" class="h-10 w-auto" onerror="this.style.display='none'" />
      <h1 class="text-2xl font-bold text-white mt-2 text-center tracking-tight">Strata</h1>
    </div>

    <div class="w-full max-w-md bg-gray-900 rounded-2xl shadow-xl border border-gray-800 p-8">
      <slot />
    </div>
  </div>
</template>
