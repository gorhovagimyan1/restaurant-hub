<script setup>
import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useCartStore } from '@/stores/cart'
import AppImage from '@/components/ui/AppImage.vue'
import { formatPrice } from '@/utils/format'

const props = defineProps({
  tableName: { type: String, default: '' },
})

const emit = defineEmits(['close', 'placed'])

const cart = useCartStore()
const { lines, currency, subtotal, serviceAmount, taxAmount, total, isEmpty } = storeToRefs(cart)

const customerName = ref('')
const notes = ref('')
const submitting = ref(false)
const error = ref(null)

async function place() {
  if (isEmpty.value || submitting.value) return
  submitting.value = true
  error.value = null
  try {
    const result = await cart.submit({ customer_name: customerName.value, notes: notes.value })
    emit('placed', result)
  } catch (err) {
    error.value = err?.response?.data?.message || 'We could not send your order. Please try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex flex-col justify-end sm:items-center sm:justify-center">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

    <!-- Sheet -->
    <div
      class="relative flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:max-w-md sm:rounded-2xl"
    >
      <header class="flex items-center justify-between border-b border-stone-200 px-5 py-3.5">
        <div>
          <h2 class="font-bold text-stone-900">Your order</h2>
          <p v-if="tableName" class="text-xs text-stone-500">{{ tableName }}</p>
        </div>
        <button class="text-stone-400 hover:text-stone-600" aria-label="Close" @click="emit('close')">✕</button>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto px-5">
        <p v-if="isEmpty" class="py-10 text-center text-sm text-stone-400">Your cart is empty.</p>

        <ul v-else class="divide-y divide-stone-100">
          <li v-for="line in lines" :key="line.product.id" class="flex items-center gap-3 py-3">
            <AppImage
              :src="line.product.image"
              :alt="line.product.name"
              class="h-12 w-12 shrink-0 rounded-lg object-cover"
            />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-stone-800">{{ line.product.name }}</p>
              <p class="text-xs text-stone-500">
                {{ formatPrice(line.product.price, currency) }}
              </p>
            </div>
            <div class="flex items-center gap-2.5 rounded-full bg-stone-100 px-1.5 py-1">
              <button
                class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none text-stone-600 hover:bg-stone-200"
                @click="cart.decrement(line.product.id)"
              >
                −
              </button>
              <span class="min-w-4 text-center text-sm font-bold tabular-nums">{{ line.quantity }}</span>
              <button
                class="grid h-6 w-6 place-items-center rounded-full text-lg leading-none text-stone-600 hover:bg-stone-200"
                @click="cart.add(line.product)"
              >
                +
              </button>
            </div>
            <span class="w-20 shrink-0 text-right text-sm font-bold text-stone-900">
              {{ formatPrice(line.product.price * line.quantity, currency) }}
            </span>
          </li>
        </ul>

        <div v-if="!isEmpty" class="mt-4 space-y-3">
          <input
            v-model="customerName"
            type="text"
            placeholder="Your name (optional)"
            class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
          />
          <textarea
            v-model="notes"
            rows="2"
            placeholder="Notes for the kitchen (optional)"
            class="w-full resize-none rounded-xl border border-stone-200 px-3.5 py-2.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
          />
        </div>
      </div>

      <footer v-if="!isEmpty" class="border-t border-stone-200 px-5 py-4">
        <dl class="space-y-1 text-sm">
          <div class="flex justify-between text-stone-500">
            <dt>Subtotal</dt>
            <dd>{{ formatPrice(subtotal, currency) }}</dd>
          </div>
          <div v-if="serviceAmount > 0" class="flex justify-between text-stone-500">
            <dt>Service charge</dt>
            <dd>{{ formatPrice(serviceAmount, currency) }}</dd>
          </div>
          <div v-if="taxAmount > 0" class="flex justify-between text-stone-500">
            <dt>Tax</dt>
            <dd>{{ formatPrice(taxAmount, currency) }}</dd>
          </div>
          <div class="flex justify-between pt-1 text-base font-bold text-stone-900">
            <dt>Total</dt>
            <dd>{{ formatPrice(total, currency) }}</dd>
          </div>
        </dl>

        <p v-if="error" class="mt-3 text-center text-sm text-red-500">{{ error }}</p>

        <button
          class="mt-3 w-full rounded-full bg-amber-500 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 disabled:opacity-60"
          :disabled="submitting"
          @click="place"
        >
          {{ submitting ? 'Sending…' : `Place order · ${formatPrice(total, currency)}` }}
        </button>
      </footer>
    </div>
  </div>
</template>
