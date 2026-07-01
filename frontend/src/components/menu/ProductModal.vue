<script setup>
import { computed, watch, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'

const store = useMenuStore()
const { selectedProduct, currency } = storeToRefs(store)

const open = computed(() => !!selectedProduct.value)

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
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
        @click.self="close"
      >
        <div
          class="relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
        >
          <!-- Close -->
          <button
            class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/40 text-lg text-white backdrop-blur transition hover:bg-black/60"
            aria-label="Close"
            @click="close"
          >
            ✕
          </button>

          <!-- Image -->
          <div class="h-56 w-full shrink-0">
            <AppImage
              :src="selectedProduct.image"
              :alt="selectedProduct.name"
              class="h-full w-full object-cover"
            />
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-5">
            <div class="flex items-start justify-between gap-4">
              <h2 class="text-xl font-bold text-stone-900">{{ selectedProduct.name }}</h2>
              <span class="shrink-0 text-lg font-bold text-amber-600">
                {{ formatPrice(selectedProduct.price, currency) }}
              </span>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
              <span
                v-if="selectedProduct.is_featured"
                class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700"
              >
                ★ Chef's pick
              </span>
              <span v-if="selectedProduct.preparation_time" class="text-stone-400">
                ⏱ {{ selectedProduct.preparation_time }} min
              </span>
              <span v-if="!selectedProduct.is_available" class="font-semibold text-red-500">
                Sold out
              </span>
            </div>

            <p v-if="selectedProduct.description" class="mt-3 text-sm leading-relaxed text-stone-600">
              {{ selectedProduct.description }}
            </p>

            <!-- Ingredients -->
            <div v-if="selectedProduct.ingredients?.length" class="mt-5">
              <h3 class="text-sm font-semibold text-stone-800">Ingredients</h3>
              <div class="mt-2 flex flex-wrap gap-2">
                <span
                  v-for="ingredient in selectedProduct.ingredients"
                  :key="ingredient"
                  class="rounded-full bg-stone-100 px-3 py-1 text-sm text-stone-600"
                >
                  {{ ingredient }}
                </span>
              </div>
            </div>
            <p v-else class="mt-5 text-sm italic text-stone-400">
              No ingredient information available.
            </p>
          </div>
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
