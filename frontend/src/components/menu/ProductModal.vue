<script setup>
import { computed, watch, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import { useDiningStore } from '@/stores/dining'
import { useCartStore } from '@/stores/cart'
import { X, Star } from 'lucide-vue-next'
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'
import { themeVars } from '@/utils/menuTheme'

const store = useMenuStore()
const dining = useDiningStore()
const cart = useCartStore()
const { selectedProduct, currency, theme } = storeToRefs(store)
const { allowOrders } = storeToRefs(dining)

const open = computed(() => !!selectedProduct.value)

// Teleported to <body>, so it sits outside the portal's themed root and has to
// carry the restaurant's design variables itself.
const themeStyle = computed(() => themeVars(theme.value))

// Ordering is offered inside the modal only when seated via a scanned QR.
const canOrder = computed(() => allowOrders.value && selectedProduct.value?.is_available)
const quantity = computed(() =>
  selectedProduct.value ? cart.quantityOf(selectedProduct.value.id) : 0,
)

function close() {
  store.closeProduct()
}

function onKeydown(event) {
  if (event.key === 'Escape') close()
}

// Lock body scroll and wire Esc only while the modal is open.
watch(open, (isOpen) => {
  if (typeof document === 'undefined') return
  document.body.style.overflow = isOpen ? 'hidden' : ''
  if (isOpen) {
    document.addEventListener('keydown', onKeydown)
  } else {
    document.removeEventListener('keydown', onKeydown)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="menu-theme fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
        :style="themeStyle"
        @click.self="close"
      >
        <div
          class="m-panel relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl shadow-2xl sm:rounded-3xl"
        >
          <!-- Close -->
          <button
            class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur transition hover:bg-black/60"
            aria-label="Close"
            @click="close"
          >
            <X :size="18" />
          </button>

          <!-- Image: contained over a blurred copy of itself, so the whole
               dish is visible however the photo was shot. -->
          <div v-if="theme.show_images" class="relative h-56 w-full shrink-0 overflow-hidden">
            <AppImage
              :src="selectedProduct.image"
              alt=""
              aria-hidden="true"
              class="absolute inset-0 h-full w-full scale-125 object-cover blur-xl"
            />
            <AppImage
              :src="selectedProduct.image"
              :alt="selectedProduct.name"
              class="relative h-full w-full object-contain"
            />
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-5" :class="!theme.show_images && 'pt-12'">
            <div class="flex items-start justify-between gap-4">
              <h2 class="m-heading text-xl font-bold">{{ selectedProduct.name }}</h2>
              <span class="m-accent shrink-0 text-lg font-bold">
                {{ formatPrice(selectedProduct.price, currency) }}
              </span>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
              <span
                v-if="selectedProduct.is_featured"
                class="m-accent-soft inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold"
              >
                <Star :size="11" style="fill: currentColor" /> Chef's pick
              </span>
              <span v-if="selectedProduct.preparation_time" class="m-faint">
                ⏱ {{ selectedProduct.preparation_time }} min
              </span>
              <span v-if="!selectedProduct.is_available" class="font-semibold text-red-500">
                Sold out
              </span>
            </div>

            <p v-if="selectedProduct.description" class="m-muted-card mt-3 text-sm leading-relaxed">
              {{ selectedProduct.description }}
            </p>

            <!-- Ingredients -->
            <div v-if="selectedProduct.ingredients?.length" class="mt-5">
              <h3 class="m-heading text-sm font-semibold">Ingredients</h3>
              <div class="mt-2 flex flex-wrap gap-2">
                <span
                  v-for="ingredient in selectedProduct.ingredients"
                  :key="ingredient"
                  class="m-elevated m-muted-card rounded-full px-3 py-1 text-sm"
                >
                  {{ ingredient }}
                </span>
              </div>
            </div>
            <p v-else class="m-faint mt-5 text-sm italic">
              No ingredient information available.
            </p>
          </div>

          <!-- Add to order -->
          <footer v-if="canOrder" class="m-card-border shrink-0 border-t p-4">
            <div v-if="quantity > 0" class="flex items-center gap-3">
              <div class="m-elevated m-muted-card flex items-center gap-3 rounded-full px-2 py-1.5">
                <button
                  class="grid h-8 w-8 place-items-center rounded-full text-xl leading-none"
                  aria-label="Remove one"
                  @click="cart.decrement(selectedProduct.id)"
                >
                  −
                </button>
                <span class="min-w-5 text-center font-bold tabular-nums">{{ quantity }}</span>
                <button
                  class="grid h-8 w-8 place-items-center rounded-full text-xl leading-none"
                  aria-label="Add one"
                  @click="cart.add(selectedProduct)"
                >
                  +
                </button>
              </div>
              <button class="m-btn flex-1 py-2.5 text-sm font-semibold" @click="close">
                Done · {{ formatPrice(selectedProduct.price * quantity, currency) }}
              </button>
            </div>
            <button
              v-else
              class="m-btn w-full py-3 text-sm font-semibold shadow-sm"
              @click="cart.add(selectedProduct)"
            >
              Add to order · {{ formatPrice(selectedProduct.price, currency) }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
