<script setup>
import { ref, computed, watchEffect } from 'vue'
import QRCode from 'qrcode'
import { X } from 'lucide-vue-next'

const props = defineProps({
  table: { type: Object, required: true },
})

const emit = defineEmits(['delete'])

// The URL a customer lands on after scanning — resolved by /t/:token.
const orderUrl = computed(() =>
  props.table.qr_token ? `${window.location.origin}/t/${props.table.qr_token}` : '',
)

const dataUrl = ref('')

watchEffect(async () => {
  if (!orderUrl.value) {
    dataUrl.value = ''
    return
  }
  try {
    dataUrl.value = await QRCode.toDataURL(orderUrl.value, {
      width: 320,
      margin: 1,
      color: { dark: '#1c1917', light: '#ffffff' },
    })
  } catch {
    dataUrl.value = ''
  }
})

function download() {
  if (!dataUrl.value) return
  const link = document.createElement('a')
  link.href = dataUrl.value
  link.download = `${props.table.name.replace(/\s+/g, '-').toLowerCase()}-qr.png`
  link.click()
}

function print() {
  if (!dataUrl.value) return
  const win = window.open('', '_blank')
  if (!win) return
  win.document.write(`
    <html><head><title>${props.table.name} — QR</title>
    <style>
      body { font-family: system-ui, sans-serif; text-align:center; padding:40px; }
      h1 { font-size:28px; margin:0 0 4px; }
      p { color:#666; margin:0 0 24px; }
      img { width:320px; height:320px; }
    </style></head>
    <body>
      <h1>${props.table.name}</h1>
      <p>Scan to view the menu &amp; order</p>
      <img src="${dataUrl.value}" />
      <script>window.onload = () => { window.print(); }<\/script>
    </body></html>
  `)
  win.document.close()
}
</script>

<template>
  <article class="flex flex-col rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-black/5">
    <div class="flex items-center justify-between">
      <h3 class="font-bold text-stone-900">{{ table.name }}</h3>
      <span
        class="rounded-full px-2 py-0.5 text-xs font-medium"
        :class="table.status === 'occupied' ? 'bg-brand-100 text-brand-700' : 'bg-stone-100 text-stone-500'"
      >
        {{ table.status }}
      </span>
    </div>

    <div class="mt-3 grid aspect-square place-items-center rounded-xl bg-stone-50 p-3">
      <img v-if="dataUrl" :src="dataUrl" :alt="`QR for ${table.name}`" class="h-full w-full object-contain" />
      <span v-else class="text-xs text-stone-400">No QR</span>
    </div>

    <p class="mt-2 truncate text-xs text-stone-400" :title="orderUrl">{{ orderUrl }}</p>

    <div class="mt-3 flex gap-2">
      <button
        class="flex-1 rounded-full bg-brand-500 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-600"
        @click="print"
      >
        Print
      </button>
      <button
        class="flex-1 rounded-full border border-stone-200 py-1.5 text-xs font-semibold text-stone-600 transition hover:bg-stone-100"
        @click="download"
      >
        Download
      </button>
      <button
        class="rounded-full border border-stone-200 px-3 py-1.5 text-xs font-semibold text-stone-400 transition hover:bg-red-50 hover:text-red-500"
        title="Delete table"
        @click="emit('delete', table)"
      >
        <X :size="14" />
      </button>
    </div>
  </article>
</template>
