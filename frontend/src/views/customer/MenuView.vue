<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import MenuItem from '@/components/menu/MenuItem.vue'

const route = useRoute()
const store = useMenuStore()
const { categories, currency } = storeToRefs(store)

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
    <div class="sticky top-14 z-20 border-b border-stone-200 bg-white/95 backdrop-blur">
      <div
        class="mx-auto flex max-w-3xl gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        <button
          v-for="category in categories"
          :key="category.id"
          class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition"
          :class="
            activeId === category.id
              ? 'bg-amber-500 text-white shadow-sm'
              : 'bg-stone-100 text-stone-600 hover:bg-stone-200'
          "
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
        <div class="flex items-baseline justify-between border-b border-stone-200 pb-2">
          <h2 :id="`cat-${category.id}`" class="text-xl font-bold text-stone-900">
            {{ category.name }}
          </h2>
          <span class="text-xs font-medium text-stone-400">{{ category.products.length }} items</span>
        </div>
        <p v-if="category.description" class="mt-2 text-sm text-stone-500">
          {{ category.description }}
        </p>

        <div class="divide-y divide-stone-100">
          <MenuItem
            v-for="product in category.products"
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
