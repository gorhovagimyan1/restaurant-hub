<script setup>
import { ref, watch, onMounted } from 'vue'
import { Search, RefreshCw, Power } from 'lucide-vue-next'
import { getUsers, updateUserStatus, roleLabel, ROLE_LABELS } from '@/services/admin'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const users = ref([])
const loading = ref(true)
const error = ref(null)
const busyId = ref(null)

const search = ref('')
const roleFilter = ref('')

const roleOptions = Object.entries(ROLE_LABELS).map(([value, label]) => ({ value, label }))

let debounce = null

async function load() {
  loading.value = true
  error.value = null
  try {
    users.value = await getUsers({
      search: search.value || undefined,
      role: roleFilter.value || undefined,
    })
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load users.'
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(load, 300)
})
watch(roleFilter, load)

function isSelf(user) {
  return user.id === auth.user?.id
}

async function toggleActive(user) {
  const next = !user.is_active
  if (!next && !window.confirm(`Deactivate ${user.full_name}? They will be signed out and unable to log in.`))
    return
  busyId.value = user.id
  try {
    const updated = await updateUserStatus(user.id, next)
    Object.assign(user, updated)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not update the user.'
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
        <h1 class="text-2xl font-bold text-stone-900">Users</h1>
        <p class="text-sm text-stone-500">Every account across the platform.</p>
      </div>
      <button
        class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-100 disabled:opacity-60"
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
        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400" />
        <input
          v-model="search"
          type="search"
          placeholder="Search by name or email…"
          class="w-full rounded-xl border border-stone-200 bg-white py-2 pl-9 pr-3 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
        />
      </div>
      <select
        v-model="roleFilter"
        class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
      >
        <option value="">All roles</option>
        <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
      </select>
    </div>

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading && !users.length" class="text-sm text-stone-400">Loading…</p>
    <p
      v-else-if="!users.length"
      class="rounded-2xl bg-white p-8 text-center text-sm text-stone-400 ring-1 ring-black/5"
    >
      No users match your filters.
    </p>

    <div v-else class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5">
      <div class="hidden overflow-x-auto sm:block">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-stone-100 text-xs uppercase tracking-wide text-stone-400">
            <tr>
              <th class="px-4 py-3 font-medium">User</th>
              <th class="px-4 py-3 font-medium">Roles</th>
              <th class="px-4 py-3 font-medium">Restaurant</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="u in users" :key="u.id" :class="busyId === u.id && 'opacity-50'">
              <td class="px-4 py-3">
                <p class="font-medium text-stone-800">{{ u.full_name }}</p>
                <p class="text-xs text-stone-400">{{ u.email }}</p>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="role in u.roles"
                    :key="role"
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                  >
                    {{ roleLabel(role) }}
                  </span>
                  <span v-if="!u.roles?.length" class="text-xs text-stone-300">—</span>
                </div>
              </td>
              <td class="px-4 py-3 text-stone-600">
                {{ u.restaurants?.map((r) => r.name).join(', ') || '—' }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="u.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-500'"
                >
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end">
                  <button
                    v-if="!isSelf(u)"
                    class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition disabled:opacity-50"
                    :class="
                      u.is_active
                        ? 'border-stone-200 text-stone-600 hover:bg-stone-100'
                        : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                    "
                    :disabled="busyId === u.id"
                    @click="toggleActive(u)"
                  >
                    <Power :size="14" />
                    {{ u.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                  <span v-else class="text-xs text-stone-300">You</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cards (mobile) -->
      <ul class="divide-y divide-stone-100 sm:hidden">
        <li v-for="u in users" :key="u.id" class="p-4" :class="busyId === u.id && 'opacity-50'">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-semibold text-stone-800">{{ u.full_name }}</p>
              <p class="truncate text-xs text-stone-400">{{ u.email }}</p>
              <div class="mt-1 flex flex-wrap gap-1">
                <span
                  v-for="role in u.roles"
                  :key="role"
                  class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                >
                  {{ roleLabel(role) }}
                </span>
              </div>
            </div>
            <span
              class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
              :class="u.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-500'"
            >
              {{ u.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <button
            v-if="!isSelf(u)"
            class="mt-3 w-full rounded-lg border px-3 py-1.5 text-xs font-medium transition disabled:opacity-50"
            :class="
              u.is_active
                ? 'border-stone-200 text-stone-600 hover:bg-stone-100'
                : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
            "
            :disabled="busyId === u.id"
            @click="toggleActive(u)"
          >
            {{ u.is_active ? 'Deactivate' : 'Activate' }}
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
