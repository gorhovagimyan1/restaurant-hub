<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ScanLine } from 'lucide-vue-next'
import { resolveTable, openSession } from '@/services/orders'
import { useDiningStore } from '@/stores/dining'
import { useCartStore } from '@/stores/cart'

/**
 * Landing target of a scanned table QR code (/t/:token).
 * Resolves the token, opens a dining session, then hands off to the full
 * restaurant portal (/r/:slug). This is the only way that portal opens.
 */
const route = useRoute()
const router = useRouter()
const dining = useDiningStore()
const cart = useCartStore()

const error = ref(null)

async function boot(token) {
  error.value = null
  try {
    const context = await resolveTable(token)
    dining.start(context)
    // Open (or re-join) this table's dining session — the session token it
    // returns is what authorizes ordering until staff settle the bill.
    const sessionToken = await openSession(token)
    dining.setSessionToken(sessionToken)
    cart.setContext(token, {
      currency: context.restaurant.currency,
      ordering: context.ordering,
      sessionToken,
    })
    router.replace({ name: 'restaurant-home', params: { slug: context.restaurant.slug } })
  } catch (err) {
    error.value =
      err?.response?.data?.message ||
      'This QR code is not valid. Please ask a staff member for help.'
  }
}

onMounted(() => boot(route.params.token))
watch(
  () => route.params.token,
  (token) => {
    if (token) boot(token)
  },
)
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-canvas px-4 text-center">
    <div v-if="error" class="max-w-md">
      <ScanLine :size="44" class="mx-auto text-ink-300" />
      <h2 class="mt-4 text-lg font-semibold text-ink-800">Table not found</h2>
      <p class="mt-1 text-sm text-ink-500">{{ error }}</p>
    </div>
    <div v-else class="flex flex-col items-center gap-3 text-ink-400">
      <span class="h-8 w-8 animate-spin rounded-full border-2 border-hairline border-t-brand-500" />
      <span class="text-sm">Opening menu…</span>
    </div>
  </div>
</template>
