<script setup>
import { computed } from 'vue'
import { Star, BellRing, ShoppingBag } from 'lucide-vue-next'
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'
import { themeVars } from '@/utils/menuTheme'

/**
 * A phone-sized rendering of the customer menu under a given theme.
 *
 * It uses the same `menu-theme` variables and `m-*` classes as the real portal,
 * so what an owner sees here is what their guests get — only smaller.
 */
const props = defineProps({
  theme: { type: Object, required: true },
  restaurant: { type: Object, default: null },
  /** Real menu data when the restaurant has any; sample dishes otherwise. */
  categories: { type: Array, default: () => [] },
})

const SAMPLE = [
  {
    id: -1,
    name: 'Starters',
    products: [
      { id: -11, name: 'Burrata & heirloom tomato', description: 'Basil oil, aged balsamic, sourdough crisp.', price: 3200, is_featured: true, image: null },
      { id: -12, name: 'Charred octopus', description: 'Smoked paprika, confit potato, lemon aioli.', price: 4800, image: null },
    ],
  },
  {
    id: -2,
    name: 'Mains',
    products: [
      { id: -21, name: 'Dry-aged ribeye', description: 'Bone marrow butter, watercress, triple-cooked chips.', price: 12500, image: null },
      { id: -22, name: 'Saffron risotto', description: 'Aged parmesan, wild mushrooms, thyme.', price: 6400, image: null },
    ],
  },
]

const style = computed(() => themeVars(props.theme))

const name = computed(() => props.restaurant?.name || 'Your Restaurant')
const currency = computed(() => props.restaurant?.currency || 'AMD')

// Fall back to sample dishes so a brand-new restaurant can still design a menu.
const sections = computed(() => {
  const real = props.categories.filter((c) => c.products?.length)
  return (real.length ? real : SAMPLE).slice(0, 2).map((category) => ({
    ...category,
    products: category.products.slice(0, 2),
  }))
})

const chips = computed(() => {
  const real = props.categories.filter((c) => c.products?.length)
  return (real.length ? real : SAMPLE).slice(0, 4)
})

const cover = computed(() => props.restaurant?.cover_image || null)
const isCompact = computed(() => props.theme.hero_style === 'compact')
</script>

<template>
  <div
    class="menu-theme menu-page relative flex h-full flex-col overflow-hidden"
    :style="style"
  >
    <!-- Top bar -->
    <div class="m-bar sticky top-0 z-10 flex items-center justify-between border-b px-3.5 py-2.5 backdrop-blur">
      <span class="m-heading truncate text-[13px] font-bold">{{ name }}</span>
      <div class="flex items-center gap-1.5">
        <span class="m-btn-quiet grid h-6 w-6 place-items-center"><BellRing :size="11" /></span>
        <span class="m-accent-soft rounded-full px-2 py-0.5 text-[10px] font-semibold">Table 4</span>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto pb-14 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      <!-- Hero -->
      <div v-if="!isCompact" class="relative aspect-[16/9] w-full overflow-hidden">
        <AppImage
          :src="cover"
          alt=""
          aria-hidden="true"
          class="absolute inset-0 h-full w-full scale-125 object-cover blur-lg"
        />
        <AppImage :src="cover" :alt="name" class="relative h-full w-full object-contain" />
        <div
          class="absolute inset-0"
          :style="
            theme.hero_style === 'gradient'
              ? {
                  backgroundImage: `linear-gradient(to top, color-mix(in oklab, ${theme.primary_color} 72%, black), color-mix(in oklab, ${theme.primary_color} 32%, transparent) 58%, color-mix(in oklab, ${theme.primary_color} 12%, transparent))`,
                }
              : { backgroundImage: 'linear-gradient(to top, rgba(0,0,0,.78), rgba(0,0,0,.15))' }
          "
        ></div>
        <div class="absolute inset-x-0 bottom-0 p-3.5">
          <p class="m-heading text-base font-bold leading-tight text-white drop-shadow-sm">{{ name }}</p>
          <p class="mt-0.5 text-[10px] text-white/85">Open now · until 23:00</p>
        </div>
      </div>
      <div v-else class="px-3.5 pt-5">
        <p class="m-heading text-lg font-bold leading-tight">{{ name }}</p>
        <p class="m-muted mt-1 text-[10px]">Open now · until 23:00</p>
        <button class="m-btn mt-3 px-4 py-1.5 text-[10px] font-semibold">View full menu</button>
      </div>

      <!-- Category rail -->
      <div class="m-bar sticky top-0 flex gap-1.5 overflow-x-auto border-b px-3.5 py-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <span
          v-for="(category, i) in chips"
          :key="category.id"
          class="m-chip shrink-0 px-2.5 py-1 text-[10px] font-medium"
          :class="i === 0 && 'm-chip-active'"
        >
          {{ category.name }}
        </span>
      </div>

      <!-- Dishes -->
      <div class="px-3.5">
        <section v-for="category in sections" :key="category.id" class="pt-4">
          <div class="m-border flex items-baseline justify-between border-b pb-1.5">
            <h3 class="m-heading text-[13px] font-bold">{{ category.name }}</h3>
            <span class="m-faint text-[9px] font-medium">{{ category.products.length }} items</span>
          </div>

          <!-- Grid layout -->
          <div v-if="theme.layout === 'grid'" class="mt-2.5 grid grid-cols-2 gap-2">
            <article
              v-for="product in category.products"
              :key="product.id"
              class="m-card overflow-hidden"
            >
              <AppImage
                v-if="theme.show_images"
                :src="product.image"
                :alt="product.name"
                class="h-14 w-full object-cover"
              />
              <div class="p-2">
                <p class="m-heading truncate text-[11px] font-semibold">{{ product.name }}</p>
                <p class="m-muted-card mt-0.5 line-clamp-2 text-[9px] leading-snug">
                  {{ product.description }}
                </p>
                <p class="mt-1.5 text-[11px] font-bold">
                  {{ formatPrice(product.price, currency) }}
                </p>
              </div>
            </article>
          </div>

          <!-- List layout -->
          <div v-else class="m-divide mt-1">
            <article
              v-for="product in category.products"
              :key="product.id"
              class="flex gap-2.5 py-2.5"
            >
              <AppImage
                v-if="theme.show_images"
                :src="product.image"
                :alt="product.name"
                class="m-radius-sm h-11 w-11 shrink-0 object-cover"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1">
                  <p class="m-heading truncate text-[11px] font-semibold">{{ product.name }}</p>
                  <Star
                    v-if="product.is_featured"
                    :size="8"
                    class="m-accent shrink-0"
                    style="fill: currentColor"
                  />
                </div>
                <p class="m-muted mt-0.5 line-clamp-2 text-[9px] leading-snug">
                  {{ product.description }}
                </p>
              </div>
              <div class="flex shrink-0 flex-col items-end gap-1">
                <span class="text-[11px] font-bold">{{ formatPrice(product.price, currency) }}</span>
                <span class="m-btn-outline px-2 py-0.5 text-[9px] font-semibold">+ Add</span>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>

    <!-- Cart bar -->
    <div class="m-bar absolute inset-x-0 bottom-0 border-t px-3.5 py-2.5 backdrop-blur">
      <div class="m-btn flex items-center justify-between px-3 py-2">
        <span class="flex items-center gap-1.5 text-[10px] font-semibold">
          <ShoppingBag :size="11" /> View order
        </span>
        <span class="text-[10px] font-semibold">{{ formatPrice(9600, currency) }}</span>
      </div>
    </div>
  </div>
</template>
