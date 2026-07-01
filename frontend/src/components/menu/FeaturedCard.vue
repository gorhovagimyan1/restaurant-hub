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
    class="flex w-64 shrink-0 cursor-pointer flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5 transition hover:shadow-md"
    @click="$emit('select', product)"
  >
    <div class="relative h-36 w-full">
      <AppImage :src="product.image" :alt="product.name" class="h-full w-full object-cover" />
      <span
        class="absolute left-3 top-3 rounded-full bg-amber-500/95 px-2 py-0.5 text-[11px] font-semibold text-white shadow"
      >
        ★ Chef's pick
      </span>
    </div>
    <div class="flex flex-1 flex-col p-4">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600">
        {{ product.category }}
      </p>
      <h3 class="mt-0.5 font-semibold text-stone-800">{{ product.name }}</h3>
      <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ product.description }}</p>
      <p class="mt-3 font-bold text-stone-900">{{ formatPrice(product.price, currency) }}</p>
    </div>
  </article>
</template>
