<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Lock, Save, RotateCcw, RefreshCw, Check } from 'lucide-vue-next'
import { getRoles, updateRolePermissions } from '@/services/admin'

const roles = ref([])
const permissions = ref([])
const loading = ref(true)
const error = ref(null)
const savingId = ref(null)

// Editable working copy: roleId -> Set(permission value).
const draft = reactive({})

function seedDraft() {
  for (const r of roles.value) draft[r.id] = new Set(r.permissions)
}

const groupedPermissions = computed(() => {
  const groups = new Map()
  for (const p of permissions.value) {
    if (!groups.has(p.group)) groups.set(p.group, [])
    groups.get(p.group).push(p)
  }
  return [...groups.entries()].map(([name, items]) => ({ name, items }))
})

function has(role, value) {
  return draft[role.id]?.has(value)
}

function toggle(role, value) {
  if (role.is_locked || savingId.value) return
  const set = draft[role.id]
  if (set.has(value)) set.delete(value)
  else set.add(value)
  draft[role.id] = new Set(set) // reassign to trigger reactivity
}

function isDirty(role) {
  if (role.is_locked) return false
  const set = draft[role.id]
  if (!set) return false
  return set.size !== role.permissions.length || role.permissions.some((p) => !set.has(p))
}

function reset(role) {
  draft[role.id] = new Set(role.permissions)
}

async function save(role) {
  savingId.value = role.id
  error.value = null
  try {
    const updated = await updateRolePermissions(role.id, [...draft[role.id]])
    const idx = roles.value.findIndex((r) => r.id === role.id)
    if (idx !== -1) roles.value[idx] = updated
    draft[role.id] = new Set(updated.permissions)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not update the role.'
  } finally {
    savingId.value = null
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await getRoles()
    roles.value = data.roles
    permissions.value = data.permissions
    seedDraft()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load roles.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">Roles &amp; Permissions</h1>
        <p class="text-sm text-ink-500">Choose which abilities each role grants.</p>
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

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading && !roles.length" class="text-sm text-ink-400">Loading…</p>

    <div
      v-else-if="roles.length"
      class="overflow-x-auto card"
    >
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="border-b border-hairline">
            <th class="sticky left-0 z-10 bg-white px-4 py-3 text-left align-bottom">
              <span class="text-xs font-medium uppercase tracking-wide text-ink-400">Permission</span>
            </th>
            <th
              v-for="role in roles"
              :key="role.id"
              class="min-w-[8.5rem] px-3 py-3 text-center align-bottom"
            >
              <div class="flex flex-col items-center gap-1">
                <span class="flex items-center gap-1 font-semibold text-ink-800">
                  {{ role.label }}
                  <Lock v-if="role.is_locked" :size="12" class="text-ink-400" />
                </span>
                <span class="text-[11px] text-ink-400">
                  {{ role.users_count }} user{{ role.users_count === 1 ? '' : 's' }}
                </span>
                <div v-if="isDirty(role)" class="mt-1 flex items-center gap-1">
                  <button
                    class="flex items-center gap-1 rounded-lg bg-brand-600 px-2 py-1 text-[11px] font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
                    :disabled="savingId === role.id"
                    @click="save(role)"
                  >
                    <Save :size="12" />
                    {{ savingId === role.id ? 'Saving…' : 'Save' }}
                  </button>
                  <button
                    class="rounded-lg border border-hairline p-1 text-ink-400 transition hover:bg-canvas"
                    :disabled="savingId === role.id"
                    title="Discard changes"
                    @click="reset(role)"
                  >
                    <RotateCcw :size="12" />
                  </button>
                </div>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groupedPermissions" :key="group.name">
            <tr class="bg-canvas/70">
              <td
                :colspan="roles.length + 1"
                class="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-ink-400"
              >
                {{ group.name }}
              </td>
            </tr>
            <tr
              v-for="perm in group.items"
              :key="perm.value"
              class="border-b border-stone-50 last:border-0 hover:bg-canvas/50"
            >
              <td class="sticky left-0 z-10 bg-white px-4 py-2.5 text-ink-700">{{ perm.label }}</td>
              <td v-for="role in roles" :key="role.id" class="px-3 py-2.5 text-center">
                <button
                  type="button"
                  class="inline-flex h-5 w-5 items-center justify-center rounded-md border transition"
                  :class="[
                    has(role, perm.value)
                      ? 'border-brand-500 bg-brand-500 text-white'
                      : 'border-hairline-strong bg-white text-transparent',
                    role.is_locked ? 'cursor-not-allowed opacity-70' : 'hover:border-brand-400',
                  ]"
                  :disabled="role.is_locked"
                  :aria-label="`${has(role, perm.value) ? 'Remove' : 'Grant'} ${perm.label} for ${role.label}`"
                  @click="toggle(role, perm.value)"
                >
                  <Check :size="13" :stroke-width="3" />
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <p class="mt-3 text-xs text-ink-400">
      The super-admin role always has full access and cannot be changed.
    </p>
  </div>
</template>
