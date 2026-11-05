<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { PackageX } from 'lucide-vue-next'
import * as api from '@/services/dashboard'

defineProps({
  dark: { type: Boolean, default: false },
})

// Stock changes with orders, not by the second: a slow poll is plenty.
const POLL_MS = 30000

const items = ref([])
let timer = null

async function refresh() {
  try {
    items.value = await api.getStockAlerts()
  } catch {
    // Keep showing the last known list; the next poll will correct it.
  }
}

onMounted(() => {
  refresh()
  timer = setInterval(refresh, POLL_MS)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <section v-if="items.length" class="mb-6">
    <div
      class="rounded-2xl p-4 ring-1"
      :class="dark ? 'bg-amber-950/40 ring-amber-500/40' : 'bg-amber-50 ring-amber-200'"
    >
      <h2
        class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide"
        :class="dark ? 'text-amber-300' : 'text-amber-700'"
      >
        <PackageX :size="16" /> Low stock
        <span class="rounded-full px-2 py-0.5 text-xs" :class="dark ? 'bg-amber-500/30' : 'bg-amber-100'">
          {{ items.length }}
        </span>
      </h2>

      <div class="flex flex-wrap gap-2">
        <div
          v-for="item in items"
          :key="item.id"
          class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm"
          :class="dark ? 'bg-stone-800' : 'bg-white shadow-sm'"
        >
          <span class="font-bold" :class="dark ? 'text-white' : 'text-ink-900'">{{ item.name }}</span>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="
              item.status === 'out'
                ? 'bg-red-500 text-white'
                : dark
                  ? 'bg-amber-500/30 text-amber-200'
                  : 'bg-amber-100 text-amber-700'
            "
          >
            {{ item.status === 'out' ? 'Out of stock' : `${item.quantity} left` }}
          </span>
        </div>
      </div>
    </div>
  </section>
</template>
