<script setup>
import { ref, onMounted } from 'vue'
import { fetchBill, requestBill } from '@/services/orders'
import { statusMeta } from '@/utils/orderStatus'
import { formatPrice } from '@/utils/format'

const props = defineProps({
  token: { type: String, required: true },
  sessionToken: { type: String, required: true },
})

const emit = defineEmits(['close'])

const bill = ref(null)
const loading = ref(true)
const error = ref(null)
const requesting = ref(false)
const requested = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    bill.value = await fetchBill(props.token, props.sessionToken)
    requested.value = !!bill.value.bill_requested
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load your bill.'
  } finally {
    loading.value = false
  }
}

async function askForBill() {
  if (requesting.value) return
  requesting.value = true
  try {
    await requestBill(props.token, props.sessionToken)
    requested.value = true
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not send the request.'
  } finally {
    requesting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="fixed inset-0 z-50 flex flex-col justify-end sm:items-center sm:justify-center">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

    <div
      class="relative flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:max-w-md sm:rounded-2xl"
    >
      <header class="flex items-center justify-between border-b border-stone-200 px-5 py-3.5">
        <div>
          <h2 class="font-bold text-stone-900">Your bill</h2>
          <p v-if="bill?.table" class="text-xs text-stone-500">{{ bill.table.name }}</p>
        </div>
        <button class="text-stone-400 hover:text-stone-600" aria-label="Close" @click="emit('close')">✕</button>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
        <p v-if="loading" class="py-8 text-center text-sm text-stone-400">Loading…</p>
        <p v-else-if="error" class="py-8 text-center text-sm text-red-500">{{ error }}</p>
        <p v-else-if="!bill.orders.length" class="py-8 text-center text-sm text-stone-400">
          You haven't ordered anything yet.
        </p>

        <div v-else class="space-y-4">
          <div v-for="order in bill.orders" :key="order.order_number">
            <div class="flex items-center justify-between">
              <p class="text-xs font-semibold text-stone-400">{{ order.order_number }}</p>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusMeta(order.status).badge"
              >
                {{ statusMeta(order.status).label }}
              </span>
            </div>
            <ul class="mt-1 space-y-0.5 text-sm">
              <li
                v-for="(item, i) in order.items"
                :key="i"
                class="flex justify-between gap-2 text-stone-600"
              >
                <span><span class="font-semibold text-stone-800">{{ item.quantity }}×</span> {{ item.product_name }}</span>
                <span class="tabular-nums text-stone-500">{{ formatPrice(item.total_price, bill.currency) }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <footer v-if="bill && bill.orders.length" class="border-t border-stone-200 px-5 py-4">
        <dl class="space-y-1 text-sm">
          <div v-if="bill.service_charge > 0" class="flex justify-between text-stone-500">
            <dt>Service charge</dt>
            <dd>{{ formatPrice(bill.service_charge, bill.currency) }}</dd>
          </div>
          <div v-if="bill.tax > 0" class="flex justify-between text-stone-500">
            <dt>Tax</dt>
            <dd>{{ formatPrice(bill.tax, bill.currency) }}</dd>
          </div>
          <div class="flex justify-between pt-1 text-base font-bold text-stone-900">
            <dt>Total</dt>
            <dd>{{ formatPrice(bill.total, bill.currency) }}</dd>
          </div>
        </dl>

        <p class="mt-2 text-center text-xs text-stone-400">Pay at the table when you're ready to leave.</p>

        <div
          v-if="requested"
          class="mt-3 rounded-full bg-emerald-50 py-2.5 text-center text-sm font-semibold text-emerald-600"
        >
          ✓ A waiter is on the way with your bill
        </div>
        <button
          v-else
          class="mt-3 w-full rounded-full bg-brand-500 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 disabled:opacity-60"
          :disabled="requesting"
          @click="askForBill"
        >
          {{ requesting ? 'Sending…' : 'Request bill' }}
        </button>
      </footer>
    </div>
  </div>
</template>
