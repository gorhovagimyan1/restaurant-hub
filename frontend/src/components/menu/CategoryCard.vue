<script setup>
import { computed } from 'vue'
import AppImage from '@/components/ui/AppImage.vue'

const props = defineProps({
  category: { type: Object, required: true },
  /** Photo-free themes fall back to a typographic tile. */
  showImage: { type: Boolean, default: true },
})

const count = computed(
  () => props.category.products_count ?? props.category.products?.length ?? 0,
)
</script>

<template>
  <article
    v-if="showImage"
    class="group m-radius relative h-40 cursor-pointer overflow-hidden sm:h-48"
  >
    <AppImage
      :src="category.image"
      :alt="category.name"
      class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
    />
    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
    <div class="absolute inset-x-0 bottom-0 p-4">
      <h3 class="m-heading text-lg font-semibold text-white drop-shadow-sm">{{ category.name }}</h3>
      <p class="text-xs font-medium text-white/80">{{ count }} items</p>
    </div>
  </article>

  <article
    v-else
    class="m-card flex h-28 cursor-pointer flex-col justify-end p-4 transition hover:-translate-y-0.5 sm:h-32"
  >
    <h3 class="m-heading text-base font-semibold">{{ category.name }}</h3>
    <p class="m-muted-card text-xs font-medium">{{ count }} items</p>
  </article>
</template>
