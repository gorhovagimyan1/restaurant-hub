<script setup>
import AppImage from '@/components/ui/AppImage.vue'

defineProps({
  category: { type: Object, required: true },
})
</script>

<template>
  <article
    class="group relative h-40 cursor-pointer overflow-hidden rounded-2xl shadow-sm ring-1 ring-black/5 transition sm:h-48"
  >
    <AppImage
      :src="category.image"
      :alt="category.name"
      class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
    />
    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
    <div class="absolute inset-x-0 bottom-0 p-4">
      <h3 class="text-lg font-semibold text-white drop-shadow-sm">{{ category.name }}</h3>
      <p class="text-xs font-medium text-white/80">
        {{ category.products_count ?? category.products?.length ?? 0 }} items
      </p>
    </div>
  </article>
</template>
