<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import * as api from '@/services/dashboard'

const emit = defineEmits(['close'])

const dashboard = useDashboardStore()

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const search = ref('')
const onlyTracked = ref(true)

const products = ref([])
// id -> input value ('' = not tracked)
const values = reactive({})
const original = {}

onMounted(async () => {
  try {
    products.value = await api.getProducts()
    for (const p of products.value) {
      const v = p.stock_quantity ?? ''
      values[p.id] = v
      original[p.id] = v
    }
    // Nothing tracked yet? Show everything so the owner can start somewhere.
    onlyTracked.value = products.value.some((p) => p.stock_quantity !== null)
  } catch {
    error.value = 'Could not load products.'
  } finally {
    loading.value = false
  }
})

const categoryName = (id) => dashboard.categories.find((c) => c.id === id)?.name ?? 'Other'

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return products.value.filter(
    (p) =>
      (!onlyTracked.value || original[p.id] !== '') &&
      (!q || p.name.toLowerCase().includes(q)),
  )
})

const changed = computed(() =>
  products.value.filter((p) => String(values[p.id]) !== String(original[p.id])),
)

function bump(id, delta) {
  const current = values[id] === '' ? 0 : Number(values[id])
  values[id] = Math.max(0, current + delta)
}

async function save() {
  if (!changed.value.length) return emit('close')
  error.value = null
  saving.value = true
  try {
    await dashboard.saveStock(
      changed.value.map((p) => ({
        id: p.id,
        stock_quantity: values[p.id] === '' || values[p.id] === null ? null : Number(values[p.id]),
      })),
    )
    emit('close')
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not update stock.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
    <div class="flex max-h-[92vh] w-full max-w-xl flex-col rounded-2xl bg-white shadow-xl">
      <div class="border-b border-hairline p-5">
        <h2 class="text-lg font-bold text-ink-900">Update stock</h2>
        <p class="text-sm text-ink-500">
          Set portions left for each item. Leave empty to stop counting. Guests never see these numbers.
        </p>
        <div class="mt-3 flex items-center gap-3">
          <input
            v-model="search"
            placeholder="Search…"
            class="min-w-0 flex-1 rounded-lg border border-hairline-strong px-3 py-2 text-sm outline-none focus:border-brand-500"
          />
          <label class="flex shrink-0 items-center gap-2 text-sm text-ink-700">
            <input v-model="onlyTracked" type="checkbox" class="h-4 w-4" /> Tracked only
          </label>
        </div>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto px-5">
        <p v-if="loading" class="py-10 text-center text-sm text-ink-400">Loading…</p>
        <ul v-else class="divide-y divide-hairline">
          <li v-for="p in visible" :key="p.id" class="flex items-center gap-3 py-2.5">
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-ink-800">{{ p.name }}</p>
              <p class="truncate text-xs text-ink-400">{{ categoryName(p.category_id) }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <button type="button" class="h-8 w-8 rounded-lg border border-hairline-strong text-ink-600 hover:bg-canvas" @click="bump(p.id, -1)">−</button>
              <input
                v-model="values[p.id]"
                type="number"
                min="0"
                placeholder="—"
                class="h-8 w-16 rounded-lg border border-hairline-strong px-2 text-center text-sm outline-none focus:border-brand-500"
              />
              <button type="button" class="h-8 w-8 rounded-lg border border-hairline-strong text-ink-600 hover:bg-canvas" @click="bump(p.id, 1)">+</button>
            </div>
          </li>
          <li v-if="!visible.length" class="py-10 text-center text-sm text-ink-400">
            Nothing to show. Untick “Tracked only” to add stock to other items.
          </li>
        </ul>
      </div>

      <div class="border-t border-hairline p-5">
        <p v-if="error" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
        <div class="flex items-center justify-end gap-2">
          <span v-if="changed.length" class="mr-auto text-xs text-ink-400">{{ changed.length }} changed</span>
          <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-ink-600 hover:bg-canvas" @click="emit('close')">
            Cancel
          </button>
          <button
            type="button"
            :disabled="saving || !changed.length"
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-60"
            @click="save"
          >
            {{ saving ? 'Saving…' : 'Save stock' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
