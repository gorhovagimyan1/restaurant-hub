<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatPrice } from '@/utils/format'
import { statusMeta, itemStatusMeta, isKitchen } from '@/utils/orderStatus'

const props = defineProps({
  order: { type: Object, required: true },
  fresh: { type: Boolean, default: false },
})

const { t } = useI18n()
const emit = defineEmits(['cancel'])

const meta = computed(() => statusMeta(props.order.status))
const canCancel = computed(() => isKitchen(props.order.status))

// A short "x min ago" label from the order's creation time.
const elapsed = computed(() => {
  const created = props.order.ordered_at || props.order.created_at
  if (!created) return ''
  const diffMs = Date.now() - new Date(created).getTime()
  const mins = Math.max(0, Math.floor(diffMs / 60000))
  if (mins < 1) return t('orders.justNow')
  if (mins < 60) return t('orders.minAgo', { n: mins })
  const hrs = Math.floor(mins / 60)
  return t('orders.hmAgo', { h: hrs, m: mins % 60 })
})
</script>

<template>
  <article
    class="card flex flex-col p-4 transition"
    :class="fresh && 'ring-2 ring-brand-400 animate-pulse-once'"
  >
    <header class="flex items-start justify-between gap-2">
      <!-- min-w-0 lets long names shrink instead of pushing the badge out. -->
      <div class="min-w-0">
        <p class="truncate text-lg font-semibold leading-tight tracking-tight text-ink-900">
          {{ order.table?.name || t('orders.table') }}
        </p>
        <p class="truncate text-xs text-ink-400">
          <span class="font-mono">{{ order.order_number }}</span> · {{ elapsed }}
        </p>
      </div>
      <span class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold" :class="meta.badge">
        {{ meta.label }}
      </span>
    </header>

    <ul class="mt-3 flex-1 space-y-1.5 text-sm">
      <li v-for="item in order.items" :key="item.id" class="flex items-start justify-between gap-2">
        <span class="min-w-0 break-words text-ink-700">
          <span class="font-semibold tabular-nums text-ink-900">{{ item.quantity }}×</span>
          {{ item.product_name }}
          <span v-if="item.notes" class="block text-xs italic text-brand-600">“{{ item.notes }}”</span>
        </span>
        <span
          v-if="item.status"
          class="shrink-0 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium"
          :class="itemStatusMeta(item.status).badge"
        >
          {{ itemStatusMeta(item.status).label }}
        </span>
      </li>
    </ul>

    <p v-if="order.customer_name || order.notes" class="mt-3 break-words rounded-xl bg-canvas px-3 py-2 text-xs text-ink-500">
      <span v-if="order.customer_name" class="font-semibold text-ink-700">{{ order.customer_name }}</span>
      <span v-if="order.customer_name && order.notes"> · </span>
      <span v-if="order.notes">{{ order.notes }}</span>
    </p>

    <div class="mt-3 flex items-center justify-between border-t border-hairline pt-3">
      <span class="whitespace-nowrap text-base font-bold tabular-nums text-ink-900">{{ formatPrice(order.total) }}</span>
      <button
        v-if="canCancel"
        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium text-ink-400 hover:bg-red-50 hover:text-red-500"
        @click="emit('cancel', order)"
      >
        {{ t('orders.cancel') }}
      </button>
    </div>
  </article>
</template>

<style scoped>
@keyframes pulse-once {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
  }
  50% {
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.35);
  }
}
.animate-pulse-once {
  animation: pulse-once 1.2s ease-in-out 2;
}
</style>
