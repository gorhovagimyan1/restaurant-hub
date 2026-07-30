<script setup>
import { watch } from 'vue'
import { storeToRefs } from 'pinia'
import { BellRing, ReceiptText } from 'lucide-vue-next'
import { useOrdersStore } from '@/stores/orders'

defineProps({
  dark: { type: Boolean, default: false },
})

const store = useOrdersStore()
const { serviceCalls, hasFreshCall } = storeToRefs(store)

// A distinct two-tone alert when a new waiter call arrives.
function alarm() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return
    const ctx = new Ctx()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.type = 'triangle'
    osc.frequency.setValueAtTime(660, ctx.currentTime)
    osc.frequency.setValueAtTime(990, ctx.currentTime + 0.15)
    osc.frequency.setValueAtTime(660, ctx.currentTime + 0.3)
    gain.gain.setValueAtTime(0.001, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5)
    osc.start()
    osc.stop(ctx.currentTime + 0.5)
  } catch {
    // silent fallback
  }
}

watch(hasFreshCall, (fresh) => {
  if (fresh) alarm()
})

async function ack(call) {
  try {
    await store.ackCall(call.id)
  } catch {
    // resurfaces on the next poll
  }
}
</script>

<template>
  <section v-if="serviceCalls.length" class="mb-6">
    <div
      class="rounded-2xl p-4 ring-1"
      :class="dark ? 'bg-red-950/40 ring-red-500/40' : 'bg-red-50 ring-red-200'"
    >
      <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide" :class="dark ? 'text-red-300' : 'text-red-600'">
        <BellRing :size="16" /> Service calls
        <span class="animate-pulse rounded-full px-2 py-0.5 text-xs" :class="dark ? 'bg-red-500/30' : 'bg-red-100'">
          {{ serviceCalls.length }}
        </span>
      </h2>

      <div class="flex flex-wrap gap-2">
        <div
          v-for="call in serviceCalls"
          :key="call.id"
          class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm"
          :class="dark ? 'bg-stone-800' : 'bg-white shadow-sm'"
        >
          <div>
            <span class="font-bold" :class="dark ? 'text-white' : 'text-ink-900'">{{ call.name }}</span>
            <span class="ml-2 text-xs" :class="dark ? 'text-ink-400' : 'text-ink-500'">
              <span v-if="call.waiter_called" class="inline-flex items-center gap-1"><BellRing :size="12" /> Waiter</span>
              <span v-if="call.waiter_called && call.bill_requested"> · </span>
              <span v-if="call.bill_requested" class="inline-flex items-center gap-1"><ReceiptText :size="12" /> Bill</span>
            </span>
          </div>
          <button
            v-if="call.waiter_called"
            class="rounded-full px-3 py-1 text-xs font-semibold text-white transition"
            :class="dark ? 'bg-red-500 hover:bg-red-400' : 'bg-red-500 hover:bg-red-600'"
            @click="ack(call)"
          >
            On my way
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
