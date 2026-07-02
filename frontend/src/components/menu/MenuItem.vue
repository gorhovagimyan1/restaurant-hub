<script setup>
import { storeToRefs } from 'pinia'
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'
import { useDiningStore } from '@/stores/dining'
import { useCartStore } from '@/stores/cart'

defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'AMD' },
})

defineEmits(['select'])

const dining = useDiningStore()
const cart = useCartStore()
const { allowOrders } = storeToRefs(dining)
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

      <div class="flex shrink-0 flex-col items-end gap-2">
        <span class="font-bold text-stone-900">{{ formatPrice(product.price, currency) }}</span>

        <!-- Ordering controls (only when seated via a scanned QR) -->
        <template v-if="allowOrders && product.is_available">
          <div
            v-if="cart.quantityOf(product.id) > 0"
            class="inline-flex items-center gap-2 rounded-full bg-amber-500 px-1.5 py-1 text-white shadow-sm"
            @click.stop
          >
            <button
              class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none transition hover:bg-amber-600"
              aria-label="Remove one"
              @click="cart.decrement(product.id)"
            >
              −
            </button>
            <span class="min-w-4 text-center text-sm font-bold tabular-nums">
              {{ cart.quantityOf(product.id) }}
            </span>
            <button
              class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none transition hover:bg-amber-600"
              aria-label="Add one"
              @click="cart.add(product)"
            >
              +
            </button>
          </div>
          <button
            v-else
            class="rounded-full border border-amber-500 px-3.5 py-1 text-sm font-semibold text-amber-600 transition hover:bg-amber-50"
            @click.stop="cart.add(product)"
          >
            + Add
          </button>
        </template>
      </div>
    </div>
  </article>
</template>
