<script setup>
import { computed, watch } from 'vue'
import { useRoute, RouterView, RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import AppImage from '@/components/ui/AppImage.vue'
import ProductModal from '@/components/menu/ProductModal.vue'

const route = useRoute()
const store = useMenuStore()
const { restaurant, loading, error } = storeToRefs(store)

const slug = computed(() => route.params.slug)
const ready = computed(() => !!restaurant.value)

watch(
  slug,
  (value) => {
    if (value) store.load(value)
  },
  { immediate: true },
)

function retry() {
  if (slug.value) store.load(slug.value, { force: true })
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-stone-50 text-stone-800">
    <!-- Top bar -->
    <header
      class="sticky top-0 z-30 border-b border-stone-200/70 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/70"
    >
      <div class="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
        <RouterLink
          :to="{ name: 'restaurant-home', params: { slug } }"
          class="flex items-center gap-2"
        >
          <AppImage
            v-if="restaurant?.logo"
            :src="restaurant.logo"
            :alt="restaurant.name"
            class="h-8 w-8 rounded-full object-cover ring-1 ring-stone-200"
          />
          <span class="text-lg font-bold tracking-tight text-stone-900">
            {{ restaurant?.name || 'Menu' }}
          </span>
        </RouterLink>

        <nav class="flex items-center gap-1 text-sm font-medium">
          <RouterLink
            :to="{ name: 'restaurant-home', params: { slug } }"
            class="rounded-full px-3 py-1.5 text-stone-600 hover:bg-stone-100"
            active-class="!bg-amber-100 !text-amber-700"
            exact-active-class="!bg-amber-100 !text-amber-700"
          >
            Home
          </RouterLink>
          <RouterLink
            :to="{ name: 'restaurant-menu', params: { slug } }"
            class="rounded-full px-3 py-1.5 text-stone-600 hover:bg-stone-100"
            active-class="!bg-amber-100 !text-amber-700"
          >
            Menu
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <!-- Loading -->
      <div v-if="loading && !ready" class="flex h-[60vh] items-center justify-center">
        <div class="flex flex-col items-center gap-3 text-stone-400">
          <span
            class="h-8 w-8 animate-spin rounded-full border-2 border-stone-200 border-t-amber-500"
          />
          <span class="text-sm">Loading menu…</span>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error && !ready" class="mx-auto max-w-md px-4 py-24 text-center">
        <p class="text-4xl">🍽️</p>
        <h2 class="mt-4 text-lg font-semibold text-stone-800">Menu unavailable</h2>
        <p class="mt-1 text-sm text-stone-500">{{ error }}</p>
        <button
          class="mt-6 rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600"
          @click="retry"
        >
          Try again
        </button>
      </div>

      <!-- Content -->
      <RouterView v-else />
    </main>

    <footer v-if="ready" class="border-t border-stone-200 bg-white">
      <div class="mx-auto max-w-3xl px-4 py-8 text-sm text-stone-500">
        <p class="font-semibold text-stone-700">{{ restaurant.name }}</p>
        <p v-if="restaurant.address" class="mt-1">
          {{ restaurant.address }}<span v-if="restaurant.city">, {{ restaurant.city }}</span>
        </p>
        <p v-if="restaurant.phone" class="mt-1">
          <a :href="`tel:${restaurant.phone}`" class="hover:text-amber-600">{{ restaurant.phone }}</a>
        </p>
        <p class="mt-4 text-xs text-stone-400">Powered by Restaurant Hub</p>
      </div>
    </footer>

    <!-- Product detail modal (shared across pages) -->
    <ProductModal />
  </div>
</template>
