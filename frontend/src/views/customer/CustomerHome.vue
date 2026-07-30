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
const { restaurant, categories, featured, currency, totalProducts, theme } = storeToRefs(store)

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

// The restaurant's chosen header treatment: a photo hero, the same photo washed
// in its brand colour, or no photo at all.
const heroStyle = computed(() => theme.value.hero_style)

// Tinting the cover in the brand colour rather than plain black. Weighted so
// the base stays dark enough for white text while the photo still reads above.
const heroOverlay = computed(() =>
  heroStyle.value === 'gradient'
    ? {
        backgroundImage: `linear-gradient(to top, color-mix(in oklab, ${theme.value.primary_color} 72%, black), color-mix(in oklab, ${theme.value.primary_color} 32%, transparent) 58%, color-mix(in oklab, ${theme.value.primary_color} 12%, transparent))`,
      }
    : {
        backgroundImage:
          'linear-gradient(to top, rgba(0,0,0,.8), rgba(0,0,0,.4) 45%, rgba(0,0,0,.2))',
      },
)

function openCategory(category) {
  router.push({ name: 'restaurant-menu', params: { slug: slug.value }, hash: `#cat-${category.id}` })
}

function openMenu() {
  router.push({ name: 'restaurant-menu', params: { slug: slug.value } })
}
</script>

<template>
  <div v-if="restaurant">
    <!-- Hero: photo, tinted photo, or compact text -->
    <section v-if="heroStyle !== 'compact'" class="relative">
      <!--
        The frame follows the photo's own 16:9 shape rather than a fixed
        height, and the photo is contained rather than cropped — a blurred copy
        of it fills whatever the frame's ratio leaves over, so the whole image
        is always visible whatever shape the restaurant uploaded.
      -->
      <div class="relative aspect-[16/9] max-h-[70vh] min-h-[17rem] w-full overflow-hidden">
        <AppImage
          :src="restaurant.cover_image"
          alt=""
          aria-hidden="true"
          class="absolute inset-0 h-full w-full scale-125 object-cover blur-2xl"
        />
        <AppImage
          :src="restaurant.cover_image"
          :alt="restaurant.name"
          class="relative h-full w-full object-contain"
        />
        <div class="absolute inset-0" :style="heroOverlay" />
        <div class="absolute inset-x-0 bottom-0">
          <div class="mx-auto max-w-3xl px-4 pb-8">
            <AppImage
              v-if="restaurant.logo"
              :src="restaurant.logo"
              :alt="restaurant.name"
              class="m-radius-sm mb-3 h-16 w-16 object-cover ring-2 ring-white/80"
            />
            <span
              v-if="statusText"
              class="mb-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur"
              :class="status?.open ? 'bg-white/20 text-white' : 'bg-black/50 text-white/90'"
            >
              <span
                class="h-1.5 w-1.5 rounded-full"
                :class="status?.open ? 'bg-white' : 'bg-red-400'"
              />
              {{ statusText }}
            </span>
            <h1 class="m-heading text-3xl font-bold text-white drop-shadow sm:text-4xl">
              {{ restaurant.name }}
            </h1>
            <p class="mt-2 max-w-xl text-sm text-white/85 sm:text-base">
              {{ restaurant.description }}
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
              <button
                class="m-btn px-6 py-2.5 text-sm font-semibold transition hover:-translate-y-0.5"
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

    <section v-else class="mx-auto max-w-3xl px-4 pt-10">
      <AppImage
        v-if="restaurant.logo"
        :src="restaurant.logo"
        :alt="restaurant.name"
        class="m-radius-sm mb-4 h-14 w-14 object-cover"
      />
      <span
        v-if="statusText"
        class="m-elevated m-muted-card mb-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
      >
        <span
          class="h-1.5 w-1.5 rounded-full"
          :style="{ backgroundColor: status?.open ? 'var(--m-primary)' : '#ef4444' }"
        />
        {{ statusText }}
      </span>
      <h1 class="m-heading text-3xl font-bold tracking-tight sm:text-4xl">{{ restaurant.name }}</h1>
      <p v-if="restaurant.description" class="m-muted mt-2 max-w-xl text-sm sm:text-base">
        {{ restaurant.description }}
      </p>
      <div class="mt-5 flex flex-wrap items-center gap-3">
        <button class="m-btn px-6 py-2.5 text-sm font-semibold" @click="openMenu">
          View full menu
        </button>
        <span class="m-faint text-xs font-medium">
          {{ totalProducts }} dishes · {{ categories.length }} categories
        </span>
      </div>
    </section>

    <div class="mx-auto max-w-3xl px-4">
      <!-- Categories -->
      <section class="pt-8">
        <div class="flex items-end justify-between">
          <div>
            <h2 class="m-heading text-xl font-bold">Browse the menu</h2>
            <p class="m-muted text-sm">Tap a category to jump straight to it.</p>
          </div>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
          <CategoryCard
            v-for="category in categories"
            :key="category.id"
            :category="category"
            :show-image="theme.show_images"
            @click="openCategory(category)"
          />
        </div>
      </section>

      <!-- Featured -->
      <section v-if="featured.length" class="pb-12 pt-10">
        <h2 class="m-heading text-xl font-bold">Chef's recommendations</h2>
        <p class="m-muted text-sm">Our most-loved dishes.</p>
        <div class="-mx-4 mt-4 flex gap-4 overflow-x-auto px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <FeaturedCard
            v-for="product in featured"
            :key="product.id"
            :product="product"
            :currency="currency"
            :show-image="theme.show_images"
            @select="store.openProduct"
          />
        </div>
      </section>
    </div>
  </div>
</template>
