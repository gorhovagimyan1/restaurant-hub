<script setup>
import { storeToRefs } from 'pinia'
import { Star } from 'lucide-vue-next'
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'
import { useDiningStore } from '@/stores/dining'
import { useCartStore } from '@/stores/cart'

/**
 * A dish on the public menu, in whichever shape the restaurant's theme asks
 * for: a scannable row or a photo-led card. Both share the same ordering
 * controls, which only appear for a guest seated via a scanned QR.
 */
defineProps({
  product: { type: Object, required: true },
  currency: { type: String, default: 'AMD' },
  layout: { type: String, default: 'list' },
  showImage: { type: Boolean, default: true },
})

defineEmits(['select'])

const dining = useDiningStore()
const cart = useCartStore()
const { allowOrders } = storeToRefs(dining)
</script>

<template>
  <!-- Grid: photo-led card -->
  <article
    v-if="layout === 'grid'"
    class="m-card flex cursor-pointer flex-col overflow-hidden transition hover:-translate-y-0.5"
    :class="{ 'opacity-60': !product.is_available }"
    @click="$emit('select', product)"
  >
    <AppImage
      v-if="showImage"
      :src="product.image"
      :alt="product.name"
      class="h-32 w-full object-cover sm:h-36"
    />

    <div class="flex flex-1 flex-col p-3.5">
      <div class="flex items-start gap-1.5">
        <h3 class="m-heading min-w-0 flex-1 font-semibold leading-tight">{{ product.name }}</h3>
        <Star
          v-if="product.is_featured"
          :size="14"
          class="m-accent mt-0.5 shrink-0"
          style="fill: currentColor"
          title="Chef's pick"
        />
      </div>
      <p class="m-muted-card mt-1 line-clamp-2 text-sm">{{ product.description }}</p>

      <div class="m-faint mt-1.5 flex items-center gap-3 text-xs">
        <span v-if="product.preparation_time">⏱ {{ product.preparation_time }} min</span>
        <span v-if="!product.is_available" class="font-semibold text-red-500">Sold out</span>
      </div>

      <div class="mt-3 flex items-center justify-between gap-2">
        <span class="font-bold">{{ formatPrice(product.price, currency) }}</span>

        <template v-if="allowOrders && product.is_available">
          <div
            v-if="cart.quantityOf(product.id) > 0"
            class="m-btn inline-flex items-center gap-1.5 px-1.5 py-1"
            @click.stop
          >
            <button
              class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none"
              aria-label="Remove one"
              @click="cart.decrement(product.id)"
            >
              −
            </button>
            <span class="min-w-4 text-center text-sm font-bold tabular-nums">
              {{ cart.quantityOf(product.id) }}
            </span>
            <button
              class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none"
              aria-label="Add one"
              @click="cart.add(product)"
            >
              +
            </button>
          </div>
          <button
            v-else
            class="m-btn-outline px-3 py-1 text-sm font-semibold"
            @click.stop="cart.add(product)"
          >
            + Add
          </button>
        </template>
      </div>
    </div>
  </article>

  <!-- List: scannable row -->
  <article
    v-else
    class="flex cursor-pointer gap-4 py-4 transition hover:opacity-80"
    :class="{ 'opacity-60': !product.is_available }"
    @click="$emit('select', product)"
  >
    <div v-if="showImage" class="relative h-20 w-20 shrink-0 sm:h-24 sm:w-24">
      <AppImage
        :src="product.image"
        :alt="product.name"
        class="m-radius-sm h-full w-full object-cover"
      />
    </div>

    <div class="flex min-w-0 flex-1 items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <h3 class="m-heading truncate font-semibold">{{ product.name }}</h3>
          <Star
            v-if="product.is_featured"
            :size="14"
            class="m-accent shrink-0"
            style="fill: currentColor"
            title="Chef's pick"
          />
        </div>
        <p class="m-muted mt-1 line-clamp-2 text-sm">{{ product.description }}</p>
        <div class="m-faint mt-1.5 flex items-center gap-3 text-xs">
          <span v-if="product.preparation_time">⏱ {{ product.preparation_time }} min</span>
          <span v-if="!product.is_available" class="font-semibold text-red-500">Sold out</span>
        </div>
      </div>

      <div class="flex shrink-0 flex-col items-end gap-2">
        <span class="font-bold">{{ formatPrice(product.price, currency) }}</span>

        <!-- Ordering controls (only when seated via a scanned QR) -->
        <template v-if="allowOrders && product.is_available">
          <div
            v-if="cart.quantityOf(product.id) > 0"
            class="m-btn inline-flex items-center gap-2 px-1.5 py-1"
            @click.stop
          >
            <button
              class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none"
              aria-label="Remove one"
              @click="cart.decrement(product.id)"
            >
              −
            </button>
            <span class="min-w-4 text-center text-sm font-bold tabular-nums">
              {{ cart.quantityOf(product.id) }}
            </span>
            <button
              class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none"
              aria-label="Add one"
              @click="cart.add(product)"
            >
              +
            </button>
          </div>
          <button
            v-else
            class="m-btn-outline px-3.5 py-1 text-sm font-semibold"
            @click.stop="cart.add(product)"
          >
            + Add
          </button>
        </template>
      </div>
    </div>
  </article>
</template>
