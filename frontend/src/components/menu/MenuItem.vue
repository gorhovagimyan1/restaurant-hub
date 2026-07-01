<script setup>
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'

defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'AMD' },
})

defineEmits(['select'])
</script>

<template>
  <article
    class="flex cursor-pointer gap-4 py-4 transition hover:opacity-80"
    :class="{ 'opacity-60': !product.is_available }"
    @click="$emit('select', product)"
  >
    <div class="relative h-20 w-20 shrink-0 sm:h-24 sm:w-24">
      <AppImage
        :src="product.image"
        :alt="product.name"
        class="h-full w-full rounded-xl object-cover"
      />
    </div>

    <div class="flex min-w-0 flex-1 items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <h3 class="truncate font-semibold text-stone-800">{{ product.name }}</h3>
          <span v-if="product.is_featured" class="text-amber-500" title="Chef's pick">★</span>
        </div>
        <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ product.description }}</p>
        <div class="mt-1.5 flex items-center gap-3 text-xs text-stone-400">
          <span v-if="product.preparation_time">⏱ {{ product.preparation_time }} min</span>
          <span v-if="!product.is_available" class="font-semibold text-red-500">Sold out</span>
        </div>
      </div>

      <div class="shrink-0 text-right">
        <span class="font-bold text-stone-900">{{ formatPrice(product.price, currency) }}</span>
      </div>
    </div>
  </article>
</template>
