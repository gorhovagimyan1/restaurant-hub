<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import MenuItem from '@/components/menu/MenuItem.vue'

const route = useRoute()
const store = useMenuStore()
const { categories, currency, theme } = storeToRefs(store)

const activeId = ref(null)
const sectionEls = new Map()
let observer = null

// Offset for the two stacked sticky bars (top header + category chips).
const SCROLL_OFFSET = 118

function registerSection(id, el) {
  if (el) sectionEls.set(id, el)
  else sectionEls.delete(id)
}

function scrollToCategory(id) {
  const el = sectionEls.get(id)
  if (!el) return
  const top = el.getBoundingClientRect().top + window.scrollY - SCROLL_OFFSET
  window.scrollTo({ top, behavior: 'smooth' })
}

onMounted(async () => {
  await nextTick()

  observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          activeId.value = Number(entry.target.dataset.catId)
        }
      }
    },
    { rootMargin: '-40% 0px -55% 0px', threshold: 0 },
  )

  sectionEls.forEach((el) => observer.observe(el))

  const hashId = route.hash ? Number(route.hash.replace('#cat-', '')) : null
  if (hashId) {
    scrollToCategory(hashId)
  } else if (categories.value.length) {
    activeId.value = categories.value[0].id
  }
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div>
    <!-- Sticky category chips -->
    <div class="m-bar sticky top-14 z-20 border-b backdrop-blur">
      <div
        class="mx-auto flex max-w-3xl gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        <button
          v-for="category in categories"
          :key="category.id"
          class="m-chip shrink-0 px-4 py-1.5 text-sm font-medium"
          :class="activeId === category.id && 'm-chip-active shadow-sm'"
          @click="scrollToCategory(category.id)"
        >
          {{ category.name }}
        </button>
      </div>
    </div>

    <!-- Menu sections -->
    <div class="mx-auto max-w-3xl px-4 pb-16">
      <section
        v-for="category in categories"
        :key="category.id"
        :ref="(el) => registerSection(category.id, el?.$el ?? el)"
        :data-cat-id="category.id"
        class="scroll-mt-32 pt-8"
      >
        <div class="m-border flex items-baseline justify-between border-b pb-2">
          <h2 :id="`cat-${category.id}`" class="m-heading text-xl font-bold">
            {{ category.name }}
          </h2>
          <span class="m-faint text-xs font-medium">{{ category.products.length }} items</span>
        </div>
        <p v-if="category.description" class="m-muted mt-2 text-sm">
          {{ category.description }}
        </p>

        <!-- Layout follows the restaurant's own menu design. -->
        <div
          :class="
            theme.layout === 'grid'
              ? 'mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2'
              : 'm-divide'
          "
        >
          <MenuItem
            v-for="product in category.products"
            :key="product.id"
            :product="product"
            :currency="currency"
            :layout="theme.layout"
            :show-image="theme.show_images"
            @select="store.openProduct"
          />
        </div>
      </section>
    </div>
  </div>
</template>
