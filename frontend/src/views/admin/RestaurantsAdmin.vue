<script setup>
import { ref, watch, onMounted } from 'vue'
import { Search, Trash2, RefreshCw, ExternalLink } from 'lucide-vue-next'
import {
  getRestaurants,
  updateRestaurantStatus,
  deleteRestaurant,
  restaurantStatusMeta,
  RESTAURANT_STATUSES,
} from '@/services/admin'

const restaurants = ref([])
const loading = ref(true)
const error = ref(null)
const busyId = ref(null)

const search = ref('')
const statusFilter = ref('')

let debounce = null

async function load() {
  loading.value = true
  error.value = null
  try {
    restaurants.value = await getRestaurants({
      search: search.value || undefined,
      status: statusFilter.value || undefined,
    })
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load restaurants.'
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(load, 300)
})
watch(statusFilter, load)

async function changeStatus(restaurant, status) {
  if (status === restaurant.status) return
  busyId.value = restaurant.id
  try {
    const updated = await updateRestaurantStatus(restaurant.id, status)
    Object.assign(restaurant, updated)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not update the restaurant.'
  } finally {
    busyId.value = null
  }
}

async function remove(restaurant) {
  if (
    !window.confirm(
      `Remove ${restaurant.name} from the platform? This soft-deletes the restaurant and hides it from everyone.`,
    )
  )
    return
  busyId.value = restaurant.id
  try {
    await deleteRestaurant(restaurant.id)
    restaurants.value = restaurants.value.filter((r) => r.id !== restaurant.id)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not remove the restaurant.'
  } finally {
    busyId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">Restaurants</h1>
        <p class="text-sm text-ink-500">Every restaurant on the platform.</p>
      </div>
      <button
        class="flex items-center gap-2 rounded-xl border border-hairline bg-white px-3.5 py-2 text-sm font-medium text-ink-600 transition hover:bg-canvas disabled:opacity-60"
        :disabled="loading"
        @click="load"
      >
        <RefreshCw :size="16" :class="loading && 'animate-spin'" />
        Refresh
      </button>
    </header>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[200px]">
        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
        <input
          v-model="search"
          type="search"
          placeholder="Search by name, slug or email…"
          class="w-full field py-2 pl-9 pr-3"
        />
      </div>
      <select
        v-model="statusFilter"
        class="field field-select"
      >
        <option value="">All statuses</option>
        <option v-for="s in RESTAURANT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
    </div>

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading && !restaurants.length" class="text-sm text-ink-400">Loading…</p>
    <p
      v-else-if="!restaurants.length"
      class="card p-8 text-center text-sm text-ink-400"
    >
      No restaurants match your filters.
    </p>

    <!-- Table (desktop) -->
    <div v-else class="overflow-hidden card">
      <div class="hidden overflow-x-auto sm:block">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-hairline text-xs uppercase tracking-wide text-ink-400">
            <tr>
              <th class="px-4 py-3 font-medium">Restaurant</th>
              <th class="px-4 py-3 font-medium">Owner</th>
              <th class="px-4 py-3 font-medium text-center">Users</th>
              <th class="px-4 py-3 font-medium text-center">Orders</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline">
            <tr v-for="r in restaurants" :key="r.id" :class="busyId === r.id && 'opacity-50'">
              <td class="px-4 py-3">
                <p class="font-medium text-ink-800">{{ r.name }}</p>
                <p class="text-xs text-ink-400">{{ r.city || '—' }}{{ r.country ? `, ${r.country}` : '' }}</p>
              </td>
              <td class="px-4 py-3">
                <template v-if="r.owner">
                  <p class="text-ink-700">{{ r.owner.full_name }}</p>
                  <p class="text-xs text-ink-400">{{ r.owner.email }}</p>
                </template>
                <span v-else class="text-ink-300">—</span>
              </td>
              <td class="px-4 py-3 text-center tabular-nums text-ink-600">{{ r.users_count }}</td>
              <td class="px-4 py-3 text-center tabular-nums text-ink-600">{{ r.orders_count }}</td>
              <td class="px-4 py-3">
                <select
                  :value="r.status"
                  :disabled="busyId === r.id"
                  class="field-select-sm rounded-lg border border-hairline bg-white py-1 pl-2 text-xs font-medium outline-none focus:border-brand-400"
                  @change="changeStatus(r, $event.target.value)"
                >
                  <option v-for="s in RESTAURANT_STATUSES" :key="s.value" :value="s.value">
                    {{ s.label }}
                  </option>
                </select>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1">
                  <a
                    :href="`/r/${r.slug}`"
                    target="_blank"
                    class="rounded-lg p-1.5 text-ink-400 transition hover:bg-canvas hover:text-ink-600"
                    title="View customer menu"
                  >
                    <ExternalLink :size="16" />
                  </a>
                  <button
                    class="rounded-lg p-1.5 text-red-400 transition hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                    :disabled="busyId === r.id"
                    title="Remove restaurant"
                    @click="remove(r)"
                  >
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cards (mobile) -->
      <ul class="divide-y divide-hairline sm:hidden">
        <li v-for="r in restaurants" :key="r.id" class="p-4" :class="busyId === r.id && 'opacity-50'">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-semibold text-ink-800">{{ r.name }}</p>
              <p class="truncate text-xs text-ink-400">{{ r.owner?.email || 'No owner' }}</p>
              <p class="mt-1 text-xs text-ink-400">
                {{ r.users_count }} users · {{ r.orders_count }} orders
              </p>
            </div>
            <span
              class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
              :class="restaurantStatusMeta(r.status).tone"
            >
              {{ restaurantStatusMeta(r.status).label }}
            </span>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <select
              :value="r.status"
              :disabled="busyId === r.id"
              class="field-select-sm flex-1 rounded-lg border border-hairline bg-white py-1.5 pl-2 text-xs font-medium outline-none focus:border-brand-400"
              @change="changeStatus(r, $event.target.value)"
            >
              <option v-for="s in RESTAURANT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <button
              class="rounded-lg border border-red-200 p-2 text-red-500 transition hover:bg-red-50 disabled:opacity-50"
              :disabled="busyId === r.id"
              @click="remove(r)"
            >
              <Trash2 :size="16" />
            </button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
