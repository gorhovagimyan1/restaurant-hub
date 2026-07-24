<script setup>
import { UtensilsCrossed, BookOpen, QrCode, LineChart } from 'lucide-vue-next'

defineProps({
  // Small heading shown above the slotted form on the right panel.
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})

const features = [
  { icon: BookOpen, text: 'Menus, categories & dishes in one place' },
  { icon: QrCode, text: 'QR ordering straight to the kitchen' },
  { icon: LineChart, text: 'Live orders, tables & daily takings' },
]
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    <!-- Brand panel -->
    <aside
      class="relative hidden overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-accent-500 p-12 text-white lg:flex lg:flex-col lg:justify-between"
    >
      <!-- soft decorative glow -->
      <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/15 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-96 w-96 translate-x-1/3 translate-y-1/3 rounded-full bg-accent-400/30 blur-3xl"></div>
      </div>

      <div class="relative flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
          <UtensilsCrossed :size="22" />
        </span>
        <span class="text-lg font-bold tracking-tight">Restaurant Hub</span>
      </div>

      <div class="relative">
        <h2 class="max-w-md text-4xl font-extrabold leading-tight tracking-tight">
          Run your restaurant, from menu to money.
        </h2>
        <ul class="mt-8 space-y-3">
          <li v-for="f in features" :key="f.text" class="flex items-center gap-3 text-white/90">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
              <component :is="f.icon" :size="18" />
            </span>
            <span class="text-sm">{{ f.text }}</span>
          </li>
        </ul>
      </div>

      <p class="relative text-xs text-white/60">© Restaurant Hub</p>
    </aside>

    <!-- Form panel -->
    <main class="flex items-center justify-center bg-white px-6 py-12">
      <div class="w-full max-w-sm">
        <!-- Compact brand mark for mobile where the panel is hidden. -->
        <div class="mb-8 flex items-center gap-2 lg:hidden">
          <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-white">
            <UtensilsCrossed :size="18" />
          </span>
          <span class="font-bold tracking-tight text-stone-900">Restaurant Hub</span>
        </div>

        <h1 v-if="title" class="text-2xl font-bold tracking-tight text-stone-900">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 text-sm text-stone-500">{{ subtitle }}</p>

        <div class="mt-6">
          <slot />
        </div>
      </div>
    </main>
  </div>
</template>
