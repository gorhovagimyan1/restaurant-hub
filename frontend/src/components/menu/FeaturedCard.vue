<script setup>
import { Star } from 'lucide-vue-next'
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'

defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'AMD' },
  showImage: { type: Boolean, default: true },
})

defineEmits(['select'])
</script>

<template>
  <article
    class="m-card flex w-64 shrink-0 cursor-pointer flex-col overflow-hidden transition hover:-translate-y-0.5"
    @click="$emit('select', product)"
  >
    <div v-if="showImage" class="relative h-36 w-full">
      <AppImage :src="product.image" :alt="product.name" class="h-full w-full object-cover" />
      <span
        class="m-btn absolute left-3 top-3 inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold shadow"
      >
        <Star :size="11" style="fill: currentColor" /> Chef's pick
      </span>
    </div>
    <div class="flex flex-1 flex-col p-4">
      <p class="m-accent text-[11px] font-semibold uppercase tracking-wide">
        {{ product.category }}
      </p>
      <h3 class="m-heading mt-0.5 font-semibold">{{ product.name }}</h3>
      <p class="m-muted-card mt-1 line-clamp-2 text-sm">{{ product.description }}</p>
      <p class="mt-3 font-bold">{{ formatPrice(product.price, currency) }}</p>
    </div>
  </article>
</template>
