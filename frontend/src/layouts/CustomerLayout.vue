<script setup>
import { computed, watch, onMounted, ref } from 'vue'
import { useRoute, RouterView, RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import { useDiningStore } from '@/stores/dining'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import AppImage from '@/components/ui/AppImage.vue'
import ProductModal from '@/components/menu/ProductModal.vue'
import CartSheet from '@/components/order/CartSheet.vue'
import BillSheet from '@/components/order/BillSheet.vue'
import { BellRing, ReceiptText, UtensilsCrossed, Check, Eye } from 'lucide-vue-next'
import { callWaiter } from '@/services/orders'
import { formatPrice } from '@/utils/format'

const route = useRoute()
const store = useMenuStore()
const dining = useDiningStore()
const cart = useCartStore()
const auth = useAuthStore()
const { restaurant, loading, error } = storeToRefs(store)
const {
  tableName,
  allowOrders,
  ordering,
  token: diningToken,
  sessionToken: diningSessionToken,
  active: diningActive,
} = storeToRefs(dining)
const { count, total, currency } = storeToRefs(cart)

const slug = computed(() => route.params.slug)
const ready = computed(() => !!restaurant.value)

const cartOpen = ref(false)
const billOpen = ref(false)
const placedOrder = ref(null)

// Staff/owner/super-admin reach this portal without a scanned-QR session — a
// browse-only preview of what customers see. Ordering, cart, bill and waiter
// call all stay hidden because there is no dining session behind them.
const isPreview = computed(() => auth.isAuthenticated && !diningActive.value)

const canCallWaiter = computed(() => diningActive.value && ordering.value?.enable_waiter_call !== false)
// Viewing the running bill stays available either way; only asking staff for it
// is gated, matching the server-side check.
const canRequestBill = computed(() => ordering.value?.enable_bill_request !== false)
const waiterCalling = ref(false)
const toast = ref(null)
let toastTimer = null

async function summonWaiter() {
  if (waiterCalling.value) return
  waiterCalling.value = true
  try {
    await callWaiter(diningToken.value, diningSessionToken.value)
    toast.value = 'A waiter is on the way'
  } catch (err) {
    toast.value = err?.response?.data?.message || 'Could not call a waiter. Please try again.'
  } finally {
    waiterCalling.value = false
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => (toast.value = null), 3500)
  }
}

// Re-establish the cart's table context from the dining session (e.g. after a
// page refresh) so ordering keeps working without re-scanning.
onMounted(() => {
  if (dining.active && cart.token !== dining.token) {
    cart.setContext(dining.token, {
      currency: dining.currency,
      ordering: dining.ordering,
      sessionToken: dining.sessionToken,
    })
  }
})

watch(
  slug,
  (value) => {
    if (value) store.load(value)
  },
  { immediate: true },
)

function retry() {
  if (slug.value) store.load(slug.value, { force: true })
}

function onPlaced(result) {
  cartOpen.value = false
  placedOrder.value = result
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-stone-50 text-stone-800">
    <!-- Top bar -->
    <header
      class="sticky top-0 z-30 border-b border-stone-200/70 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/70"
    >
      <div class="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
        <RouterLink
          :to="{ name: 'restaurant-home', params: { slug } }"
          class="flex min-w-0 flex-1 items-center gap-2"
        >
          <AppImage
            v-if="restaurant?.logo"
            :src="restaurant.logo"
            :alt="restaurant.name"
            class="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-stone-200"
          />
          <span class="truncate text-base font-bold tracking-tight text-stone-900 sm:text-lg">
            {{ restaurant?.name || 'Menu' }}
          </span>
        </RouterLink>

        <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
          <nav class="flex items-center gap-1 text-sm font-medium">
            <RouterLink
              :to="{ name: 'restaurant-home', params: { slug } }"
              class="hidden rounded-full px-2.5 py-1.5 text-stone-600 hover:bg-stone-100 sm:block sm:px-3"
              active-class="!bg-brand-100 !text-brand-700"
              exact-active-class="!bg-brand-100 !text-brand-700"
            >
              Home
            </RouterLink>
            <RouterLink
              :to="{ name: 'restaurant-menu', params: { slug } }"
              class="rounded-full px-2.5 py-1.5 text-stone-600 hover:bg-stone-100 sm:px-3"
              active-class="!bg-brand-100 !text-brand-700"
            >
              Menu
            </RouterLink>
          </nav>
          <button
            v-if="canCallWaiter"
            class="flex items-center gap-1 rounded-full border border-stone-200 px-2.5 py-1.5 text-xs font-semibold text-stone-600 transition hover:bg-stone-100 disabled:opacity-50"
            :disabled="waiterCalling"
            aria-label="Call waiter"
            @click="summonWaiter"
          >
            <BellRing :size="15" /> <span class="hidden sm:inline">Waiter</span>
          </button>
          <button
            v-if="diningActive"
            class="flex items-center gap-1 rounded-full border border-stone-200 px-2.5 py-1.5 text-xs font-semibold text-stone-600 transition hover:bg-stone-100"
            aria-label="Request bill"
            @click="billOpen = true"
          >
            <ReceiptText :size="15" /> <span class="hidden sm:inline">Bill</span>
          </button>
          <span
            v-if="tableName"
            class="shrink-0 rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700 sm:px-3"
          >
            {{ tableName }}
          </span>
          <span
            v-else-if="isPreview"
            class="flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 sm:px-3"
          >
            <Eye :size="13" /> Preview
          </span>
        </div>
      </div>
    </header>

    <!-- Staff preview notice: this portal is normally reached by scanning a
         table QR; here it is opened from the dashboard, so ordering is off. -->
    <div
      v-if="isPreview"
      class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs font-medium text-amber-800"
    >
      Staff preview — this is what customers see after scanning a table QR. Ordering is disabled here.
    </div>

    <main class="flex-1" :class="{ 'pb-24': allowOrders && count > 0 }">
      <!-- Loading -->
      <div v-if="loading && !ready" class="flex h-[60vh] items-center justify-center">
        <div class="flex flex-col items-center gap-3 text-stone-400">
          <span
            class="h-8 w-8 animate-spin rounded-full border-2 border-stone-200 border-t-brand-500"
          />
          <span class="text-sm">Loading menu…</span>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error && !ready" class="mx-auto max-w-md px-4 py-24 text-center">
        <UtensilsCrossed :size="44" class="mx-auto text-stone-300" />
        <h2 class="mt-4 text-lg font-semibold text-stone-800">Menu unavailable</h2>
        <p class="mt-1 text-sm text-stone-500">{{ error }}</p>
        <button
          class="mt-6 rounded-full bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600"
          @click="retry"
        >
          Try again
        </button>
      </div>

      <!-- Content -->
      <RouterView v-else />
    </main>

    <footer v-if="ready" class="border-t border-stone-200 bg-white">
      <div class="mx-auto max-w-3xl px-4 py-8 text-sm text-stone-500">
        <p class="font-semibold text-stone-700">{{ restaurant.name }}</p>
        <p v-if="restaurant.address" class="mt-1">
          {{ restaurant.address }}<span v-if="restaurant.city">, {{ restaurant.city }}</span>
        </p>
        <p v-if="restaurant.phone" class="mt-1">
          <a :href="`tel:${restaurant.phone}`" class="hover:text-brand-600">{{ restaurant.phone }}</a>
        </p>
        <p class="mt-4 text-xs text-stone-400">Powered by Restaurant Hub</p>
      </div>
    </footer>

    <!-- Floating cart bar -->
    <div
      v-if="allowOrders && count > 0"
      class="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-white/95 backdrop-blur"
    >
      <div class="mx-auto max-w-3xl px-4 py-3">
        <button
          class="flex w-full items-center justify-between rounded-full bg-brand-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600"
          @click="cartOpen = true"
        >
          <span class="flex items-center gap-2">
            <span class="grid h-6 w-6 place-items-center rounded-full bg-white/25 text-xs font-bold">
              {{ count }}
            </span>
            View order
          </span>
          <span>{{ formatPrice(total, currency) }}</span>
        </button>
      </div>
    </div>

    <CartSheet
      v-if="cartOpen"
      :table-name="tableName"
      @close="cartOpen = false"
      @placed="onPlaced"
    />

    <BillSheet
      v-if="billOpen && diningToken && diningSessionToken"
      :token="diningToken"
      :session-token="diningSessionToken"
      :can-request="canRequestBill"
      @close="billOpen = false"
    />

    <!-- Order confirmation -->
    <div
      v-if="placedOrder"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-6"
      @click.self="placedOrder = null"
    >
      <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-100 text-brand-600"><Check :size="32" :stroke-width="2.5" /></div>
        <h2 class="mt-4 text-xl font-bold text-stone-900">Order sent!</h2>
        <p class="mt-1 text-sm text-stone-500">
          {{ tableName }} · your order is on its way to the kitchen.
        </p>
        <div class="mt-4 rounded-xl bg-stone-50 p-3">
          <p class="text-xs uppercase tracking-wide text-stone-400">Order number</p>
          <p class="mt-1 text-lg font-bold text-stone-900">{{ placedOrder.order_number }}</p>
          <p class="mt-1 text-sm text-stone-500">
            Total <span class="font-semibold text-stone-800">{{ formatPrice(placedOrder.total, currency) }}</span>
          </p>
        </div>
        <button
          class="mt-5 w-full rounded-full bg-brand-500 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600"
          @click="placedOrder = null"
        >
          Order more
        </button>
      </div>
    </div>

    <!-- Toast (e.g. waiter called) -->
    <Transition name="toast">
      <div
        v-if="toast"
        class="fixed inset-x-0 bottom-24 z-50 mx-auto w-fit max-w-[90%] rounded-full bg-stone-900 px-5 py-3 text-center text-sm font-semibold text-white shadow-lg"
      >
        {{ toast }}
      </div>
    </Transition>

    <!-- Product detail modal (shared across pages) -->
    <ProductModal />
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
