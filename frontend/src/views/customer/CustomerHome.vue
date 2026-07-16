<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import AppImage from '@/components/ui/AppImage.vue'
import CategoryCard from '@/components/menu/CategoryCard.vue'
import FeaturedCard from '@/components/menu/FeaturedCard.vue'

const route = useRoute()
const router = useRouter()
const store = useMenuStore()
const { restaurant, categories, featured, currency, totalProducts } = storeToRefs(store)

const slug = computed(() => route.params.slug)

// Open/closed status resolved server-side (weekly hours + holiday overrides).
const status = computed(() => restaurant.value?.open_status || null)

const statusText = computed(() => {
  const s = status.value
  if (!s) return null
  if (s.open) return s.closes_at ? `Open now · until ${s.closes_at}` : 'Open now'
  const today = s.today
  if (today && !today.is_closed && today.open_time) {
    return `Closed · opens ${today.open_time}`
  }
  return today?.label ? `Closed · ${today.label}` : 'Closed today'
})

function openCategory(category) {
  router.push({ name: 'restaurant-menu', params: { slug: slug.value }, hash: `#cat-${category.id}` })
}

function openMenu() {
  router.push({ name: 'restaurant-menu', params: { slug: slug.value } })
}
</script>

<template>
  <div v-if="restaurant">
    <!-- Hero -->
    <section class="relative">
      <div class="relative h-72 w-full overflow-hidden sm:h-96">
        <AppImage
          :src="restaurant.cover_image"
          :alt="restaurant.name"
          class="h-full w-full object-cover"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20" />
        <div class="absolute inset-x-0 bottom-0">
          <div class="mx-auto max-w-3xl px-4 pb-8">
            <AppImage
              v-if="restaurant.logo"
              :src="restaurant.logo"
              :alt="restaurant.name"
              class="mb-3 h-16 w-16 rounded-2xl object-cover ring-2 ring-white/80"
            />
            <span
              v-if="statusText"
              class="mb-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur"
              :class="status?.open ? 'bg-green-500/90 text-white' : 'bg-black/50 text-white/90'"
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="status?.open ? 'bg-white' : 'bg-red-400'"
              />
              {{ statusText }}
            </span>
            <h1 class="text-3xl font-bold text-white drop-shadow sm:text-4xl">
              {{ restaurant.name }}
            </h1>
            <p class="mt-2 max-w-xl text-sm text-white/85 sm:text-base">
              {{ restaurant.description }}
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
              <button
                class="rounded-full bg-amber-500 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-amber-500/30 transition hover:bg-amber-600"
                @click="openMenu"
              >
                View full menu
              </button>
              <span class="text-xs font-medium text-white/70">
                {{ totalProducts }} dishes · {{ categories.length }} categories
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="mx-auto max-w-3xl px-4">
      <!-- Categories -->
      <section class="pt-8">
        <div class="flex items-end justify-between">
          <div>
            <h2 class="text-xl font-bold text-stone-900">Browse the menu</h2>
            <p class="text-sm text-stone-500">Tap a category to jump straight to it.</p>
          </div>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
          <CategoryCard
            v-for="category in categories"
            :key="category.id"
            :category="category"
            @click="openCategory(category)"
          />
        </div>
      </section>

      <!-- Featured -->
      <section v-if="featured.length" class="pb-12 pt-10">
        <h2 class="text-xl font-bold text-stone-900">Chef's recommendations</h2>
        <p class="text-sm text-stone-500">Our most-loved dishes.</p>
        <div class="-mx-4 mt-4 flex gap-4 overflow-x-auto px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <FeaturedCard
            v-for="product in featured"
            :key="product.id"
            :product="product"
            :currency="currency"
            @select="store.openProduct"
          />
        </div>
      </section>
    </div>
  </div>
</template>
