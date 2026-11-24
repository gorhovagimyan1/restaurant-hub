<script setup>
import { computed, watch, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, RouterView, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/stores/menu'
import { useDiningStore } from '@/stores/dining'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { useOrderTrackingStore } from '@/stores/orderTracking'
import AppImage from '@/components/ui/AppImage.vue'
import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue'
import ProductModal from '@/components/menu/ProductModal.vue'
import CartSheet from '@/components/order/CartSheet.vue'
import BillSheet from '@/components/order/BillSheet.vue'
import OrderTracker from '@/components/order/OrderTracker.vue'
import RateDishesSheet from '@/components/order/RateDishesSheet.vue'
import { BellRing, ReceiptText, UtensilsCrossed, Check, Eye, CookingPot } from 'lucide-vue-next'
import { callWaiter } from '@/services/orders'
import { formatPrice } from '@/utils/format'
import { themeVars, ensureThemeFonts, isDarkTheme } from '@/utils/menuTheme'

const { t } = useI18n()
const route = useRoute()
const store = useMenuStore()
const dining = useDiningStore()
const cart = useCartStore()
const auth = useAuthStore()
const { restaurant, loading, error, theme } = storeToRefs(store)
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

// The restaurant's design, as CSS custom properties. Everything under
// `.menu-theme` derives its palette from these — see assets/main.css.
const themeStyle = computed(() => themeVars(theme.value))
const switcherTone = computed(() => (isDarkTheme(theme.value) ? 'dark' : 'light'))
watch(theme, (value) => ensureThemeFonts(value), { immediate: true })

const tracking = useOrderTrackingStore()
const { activeCount, count: orderCount, orders: trackedOrders } = storeToRefs(tracking)

// Dishes served and not yet rated. A gentle, dismissible nudge: never blocks
// ordering, the bill or calling a waiter, and stays gone once dismissed.
const DISMISS_KEY = 'rh_rate_prompt_dismissed'
const ratePromptDismissed = ref(readDismissed())

function readDismissed() {
  try {
    return sessionStorage.getItem(DISMISS_KEY) === dining.sessionToken
  } catch {
    return false
  }
}

const toRateCount = computed(() =>
  trackedOrders.value.reduce((n, order) => n + order.items.filter((i) => i.can_review).length, 0),
)
const showRatePrompt = computed(() => diningActive.value && toRateCount.value > 0 && !ratePromptDismissed.value)

function dismissRatePrompt() {
  ratePromptDismissed.value = true
  try {
    sessionStorage.setItem(DISMISS_KEY, dining.sessionToken || '1')
  } catch {
    // dismissed for this page view only
  }
}

function openRatings() {
  rateOpen.value = true
}

const cartOpen = ref(false)
const billOpen = ref(false)
const trackerOpen = ref(false)
const rateOpen = ref(false)
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
    toast.value = t('customer.waiterOnWay')
  } catch (err) {
    toast.value = err?.response?.data?.message || t('customer.waiterFailed')
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

  // Pick the guest's orders back up after a refresh, so a returning phone
  // shows current progress without them having to ask for it.
  if (dining.active && dining.sessionToken) {
    tracking.startPolling()
  }
})

onBeforeUnmount(() => tracking.stopPolling())

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
  // Begin following the round they just sent.
  tracking.trackNew()
}

// From the confirmation straight into "where is it?".
function trackFromConfirmation() {
  placedOrder.value = null
  trackerOpen.value = true
}
</script>

<template>
  <div class="menu-theme menu-page flex min-h-screen flex-col" :style="themeStyle">
    <!-- Language: a slim strip on phones (the header is full), in the header from sm up. -->
    <div class="m-bar m-card-border flex justify-end border-b px-4 py-1.5 sm:hidden">
      <LanguageSwitcher :tone="switcherTone" />
    </div>

    <!-- Top bar -->
    <header class="m-bar sticky top-0 z-30 border-b backdrop-blur">
      <div class="mx-auto flex h-14 max-w-3xl items-center justify-between px-4">
        <RouterLink
          :to="{ name: 'restaurant-home', params: { slug } }"
          class="flex min-w-0 flex-1 items-center gap-2"
        >
          <!--
            The logo yields on the narrowest phones: with the service buttons
            alongside it there isn't room for both, and the name identifies the
            restaurant better than a 32px crop of its logo does.
          -->
          <AppImage
            v-if="restaurant?.logo"
            :src="restaurant.logo"
            :alt="restaurant.name"
            class="m-card-border hidden h-8 w-8 shrink-0 rounded-full object-cover ring-1 min-[420px]:block"
          />
          <span class="m-heading truncate text-base font-bold tracking-tight sm:text-lg">
            {{ restaurant?.name || t('customer.menuFallback') }}
          </span>
        </RouterLink>

        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
          <nav class="flex items-center gap-1 text-sm font-medium">
            <RouterLink
              :to="{ name: 'restaurant-home', params: { slug } }"
              class="m-muted-card hidden rounded-full px-2.5 py-1.5 sm:block sm:px-3"
              active-class="m-accent-soft"
              exact-active-class="m-accent-soft"
            >
              {{ t('customer.home') }}
            </RouterLink>
            <RouterLink
              :to="{ name: 'restaurant-menu', params: { slug } }"
              class="m-muted-card rounded-full px-2.5 py-1.5 sm:px-3"
              active-class="m-accent-soft"
            >
              {{ t('customer.menu') }}
            </RouterLink>
          </nav>
          <button
            v-if="canCallWaiter"
            class="m-btn-quiet flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50"
            :disabled="waiterCalling"
            :aria-label="t('customer.callWaiter')"
            @click="summonWaiter"
          >
            <BellRing :size="15" /> <span class="hidden sm:inline">{{ t('customer.waiter') }}</span>
          </button>
          <!-- Order progress. Only offered once something has been ordered. -->
          <button
            v-if="diningActive && orderCount"
            class="m-btn-quiet relative flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold"
            :aria-label="t('customer.trackOrderAria')"
            @click="trackerOpen = true"
          >
            <CookingPot :size="15" /> <span class="hidden sm:inline">{{ t('customer.order') }}</span>
            <span
              v-if="activeCount"
              class="absolute -right-1 -top-1 grid h-4 min-w-4 place-items-center rounded-full px-1 text-[10px] font-bold"
              :style="{
                backgroundColor: 'var(--m-primary)',
                color: 'var(--m-primary-contrast)',
              }"
            >
              {{ activeCount }}
            </span>
          </button>
          <button
            v-if="diningActive"
            class="m-btn-quiet flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold"
            :aria-label="t('customer.requestBill')"
            @click="billOpen = true"
          >
            <ReceiptText :size="15" /> <span class="hidden sm:inline">{{ t('customer.bill') }}</span>
          </button>
          <span
            v-if="tableName"
            class="m-accent-soft shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold sm:px-3"
          >
            {{ tableName }}
          </span>
          <span
            v-else-if="isPreview"
            class="flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 sm:px-3"
          >
            <Eye :size="13" /> {{ t('customer.preview') }}
          </span>
          <LanguageSwitcher :tone="switcherTone" class="hidden sm:inline-flex" />
        </div>
      </div>
    </header>

    <!-- Staff preview notice: this portal is normally reached by scanning a
         table QR; here it is opened from the dashboard, so ordering is off. -->
    <div
      v-if="isPreview"
      class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs font-medium text-amber-800"
    >
      {{ t('customer.previewNotice') }}
    </div>

    <!-- Gentle nudge to rate served dishes -->
    <div
      v-if="showRatePrompt"
      class="m-bar m-card-border flex items-center justify-center gap-3 border-b px-4 py-2 text-xs"
    >
      <span class="m-muted">
        {{ t('customer.rateNudge', { count: toRateCount }, toRateCount) }}
      </span>
      <button class="m-accent font-semibold hover:underline" @click="openRatings">{{ t('customer.rate') }}</button>
      <button class="m-faint hover:opacity-70" :aria-label="t('customer.dismiss')" @click="dismissRatePrompt">✕</button>
    </div>

    <main class="flex-1" :class="{ 'pb-24': allowOrders && count > 0 }">
      <!-- Loading -->
      <div v-if="loading && !ready" class="flex h-[60vh] items-center justify-center">
        <div class="m-muted flex flex-col items-center gap-3">
          <span
            class="m-border h-8 w-8 animate-spin rounded-full border-2"
            style="border-top-color: var(--m-primary)"
          />
          <span class="text-sm">{{ t('customer.loadingMenu') }}</span>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="error && !ready" class="mx-auto max-w-md px-4 py-24 text-center">
        <UtensilsCrossed :size="44" class="m-faint mx-auto" />
        <h2 class="m-heading mt-4 text-lg font-semibold">{{ t('customer.menuUnavailable') }}</h2>
        <p class="m-muted mt-1 text-sm">{{ error }}</p>
        <button class="m-btn mt-6 px-5 py-2 text-sm font-semibold" @click="retry">
          {{ t('customer.tryAgain') }}
        </button>
      </div>

      <!-- Content -->
      <RouterView v-else />
    </main>

    <footer v-if="ready" class="m-panel m-card-border border-t">
      <div class="m-muted-card mx-auto max-w-3xl px-4 py-8 text-sm">
        <p class="m-heading font-semibold" style="color: var(--m-text-card)">
          {{ restaurant.name }}
        </p>
        <p v-if="restaurant.address" class="mt-1">
          {{ restaurant.address }}<span v-if="restaurant.city">, {{ restaurant.city }}</span>
        </p>
        <p v-if="restaurant.phone" class="mt-1">
          <a :href="`tel:${restaurant.phone}`" class="hover:underline">{{ restaurant.phone }}</a>
        </p>
        <p class="m-faint mt-4 text-xs">{{ t('customer.poweredBy') }}</p>
      </div>
    </footer>

    <!-- Floating cart bar -->
    <div v-if="allowOrders && count > 0" class="m-bar fixed inset-x-0 bottom-0 z-40 border-t backdrop-blur">
      <div class="mx-auto max-w-3xl px-4 py-3">
        <button
          class="m-btn flex w-full items-center justify-between px-5 py-3 text-sm font-semibold shadow-sm"
          @click="cartOpen = true"
        >
          <span class="flex items-center gap-2">
            <span class="grid h-6 w-6 place-items-center rounded-full bg-white/25 text-xs font-bold">
              {{ count }}
            </span>
            {{ t('customer.viewOrder') }}
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

    <RateDishesSheet v-if="rateOpen" @close="rateOpen = false" />

    <OrderTracker
      v-if="trackerOpen"
      :table-name="tableName"
      @close="trackerOpen = false"
    />

    <!-- Order confirmation -->
    <div
      v-if="placedOrder"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-6"
      @click.self="placedOrder = null"
    >
      <div class="m-card w-full max-w-sm p-6 text-center shadow-xl">
        <div class="m-accent-soft mx-auto grid h-16 w-16 place-items-center rounded-full">
          <Check :size="32" :stroke-width="2.5" />
        </div>
        <h2 class="m-heading mt-4 text-xl font-bold">{{ t('customer.orderSent') }}</h2>
        <p class="m-muted-card mt-1 text-sm">
          {{ tableName ? t('customer.orderOnWay', { table: tableName }) : t('customer.orderOnWayNoTable') }}
        </p>
        <div class="m-elevated m-radius-sm mt-4 p-3">
          <p class="m-faint text-xs uppercase tracking-wide">{{ t('customer.orderNumber') }}</p>
          <p class="m-heading mt-1 text-lg font-bold">{{ placedOrder.order_number }}</p>
          <p class="m-muted-card mt-1 text-sm">
            {{ t('customer.total') }}
            <span class="font-semibold" style="color: var(--m-text-card)">
              {{ formatPrice(placedOrder.total, currency) }}
            </span>
          </p>
        </div>
        <div class="mt-5 flex gap-2">
          <button
            class="m-btn-quiet flex-1 py-2.5 text-sm font-semibold"
            @click="placedOrder = null"
          >
            {{ t('customer.orderMore') }}
          </button>
          <button class="m-btn flex-1 py-2.5 text-sm font-semibold" @click="trackFromConfirmation">
            {{ t('customer.trackOrder') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast (e.g. waiter called) -->
    <Transition name="toast">
      <div
        v-if="toast"
        class="fixed inset-x-0 bottom-24 z-50 mx-auto w-fit max-w-[90%] rounded-full px-5 py-3 text-center text-sm font-semibold shadow-lg"
        style="background-color: var(--m-text); color: var(--m-surface)"
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
