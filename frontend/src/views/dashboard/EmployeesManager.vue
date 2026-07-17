<script setup>
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import {
  fetchEmployees,
  inviteEmployee,
  updateEmployee,
  deleteEmployee,
  ASSIGNABLE_ROLES,
  roleLabel,
} from '@/services/employees'

const auth = useAuthStore()
const { user } = storeToRefs(auth)

const employees = ref([])
const loading = ref(true)
const error = ref(null)
const notice = ref(null)

const form = ref({ first_name: '', last_name: '', email: '', phone: '', role: 'waiter' })
const inviting = ref(false)
const formErrors = ref({})

// Rows the current user may edit: never yourself, never an owner.
function isManageable(employee) {
  return employee.email !== user.value?.email && employee.role !== 'restaurant-owner'
}

const staffCount = computed(() => employees.value.length)

async function load() {
  loading.value = true
  error.value = null
  try {
    employees.value = await fetchEmployees()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load employees.'
  } finally {
    loading.value = false
  }
}

async function invite() {
  formErrors.value = {}
  notice.value = null
  if (inviting.value) return
  inviting.value = true
  try {
    await inviteEmployee({
      first_name: form.value.first_name.trim(),
      last_name: form.value.last_name.trim(),
      email: form.value.email.trim(),
      phone: form.value.phone.trim() || null,
      role: form.value.role,
    })
    notice.value = `Invitation sent to ${form.value.email.trim()} — they'll get an email to set their password.`
    form.value = { first_name: '', last_name: '', email: '', phone: '', role: 'waiter' }
    await load()
  } catch (err) {
    formErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(formErrors.value).length) {
      error.value = err?.response?.data?.message || 'Could not invite the employee.'
    }
  } finally {
    inviting.value = false
  }
}

async function changeRole(employee, role) {
  try {
    const updated = await updateEmployee(employee.id, { role, is_active: employee.is_active })
    Object.assign(employee, updated)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not update the role.'
    await load()
  }
}

async function toggleActive(employee) {
  try {
    const updated = await updateEmployee(employee.id, {
      role: employee.role,
      is_active: !employee.is_active,
    })
    Object.assign(employee, updated)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not change the status.'
    await load()
  }
}

async function remove(employee) {
  if (!window.confirm(`Remove ${employee.full_name} from the team? They will lose access.`)) return
  try {
    await deleteEmployee(employee.id)
    employees.value = employees.value.filter((e) => e.id !== employee.id)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not remove the employee.'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <header class="mb-5">
      <h1 class="text-2xl font-bold text-stone-900">Team</h1>
      <p class="text-sm text-stone-500">
        Invite staff and set what they can do. Waiters and kitchen staff get their own dashboards.
      </p>
    </header>

    <!-- Invite form -->
    <form
      class="mb-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5"
      @submit.prevent="invite"
    >
      <p class="mb-3 text-sm font-semibold text-stone-700">Invite an employee</p>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <label class="block text-xs font-medium text-stone-500">First name</label>
          <input
            v-model="form.first_name"
            type="text"
            required
            class="mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          />
          <p v-if="formErrors.first_name" class="mt-1 text-xs text-red-600">
            {{ formErrors.first_name[0] }}
          </p>
        </div>
        <div>
          <label class="block text-xs font-medium text-stone-500">Last name</label>
          <input
            v-model="form.last_name"
            type="text"
            required
            class="mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          />
          <p v-if="formErrors.last_name" class="mt-1 text-xs text-red-600">
            {{ formErrors.last_name[0] }}
          </p>
        </div>
        <div>
          <label class="block text-xs font-medium text-stone-500">Email</label>
          <input
            v-model="form.email"
            type="email"
            required
            class="mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          />
          <p v-if="formErrors.email" class="mt-1 text-xs text-red-600">
            {{ formErrors.email[0] }}
          </p>
        </div>
        <div>
          <label class="block text-xs font-medium text-stone-500">Phone (optional)</label>
          <input
            v-model="form.phone"
            type="text"
            class="mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-stone-500">Role</label>
          <select
            v-model="form.role"
            class="mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          >
            <option v-for="r in ASSIGNABLE_ROLES" :key="r.value" :value="r.value">
              {{ r.label }}
            </option>
          </select>
        </div>
        <div class="flex items-end">
          <button
            type="submit"
            :disabled="inviting"
            class="w-full rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
          >
            {{ inviting ? 'Sending…' : 'Send invite' }}
          </button>
        </div>
      </div>
      <p v-if="notice" class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
        {{ notice }}
      </p>
    </form>

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
      {{ error }}
    </p>

    <!-- Staff list -->
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5">
      <div class="flex items-center justify-between border-b border-stone-100 px-4 py-3">
        <p class="text-sm font-semibold text-stone-700">Team members</p>
        <span class="text-xs text-stone-400">{{ staffCount }} total</span>
      </div>

      <p v-if="loading" class="px-4 py-6 text-sm text-stone-400">Loading…</p>
      <p v-else-if="!employees.length" class="px-4 py-6 text-sm text-stone-400">
        No employees yet. Invite your first team member above.
      </p>

      <ul v-else class="divide-y divide-stone-100">
        <li
          v-for="employee in employees"
          :key="employee.id"
          class="flex flex-wrap items-center gap-3 px-4 py-3"
        >
          <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-stone-900">
              {{ employee.full_name }}
              <span
                v-if="employee.email === user?.email"
                class="ml-1 rounded bg-stone-100 px-1.5 py-0.5 text-xs font-normal text-stone-500"
                >You</span
              >
            </p>
            <p class="truncate text-xs text-stone-400">{{ employee.email }}</p>
          </div>

          <!-- Owner / self: read-only role badge -->
          <span
            v-if="!isManageable(employee)"
            class="rounded-lg bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-600"
          >
            {{ employee.role === 'restaurant-owner' ? 'Owner' : roleLabel(employee.role) }}
          </span>

          <!-- Manageable staff: editable role -->
          <select
            v-else
            :value="employee.role"
            class="rounded-lg border border-stone-200 px-2.5 py-1 text-xs outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
            @change="changeRole(employee, $event.target.value)"
          >
            <option v-for="r in ASSIGNABLE_ROLES" :key="r.value" :value="r.value">
              {{ r.label }}
            </option>
          </select>

          <button
            v-if="isManageable(employee)"
            class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
            :class="
              employee.is_active
                ? 'bg-green-50 text-green-700 hover:bg-green-100'
                : 'bg-stone-100 text-stone-500 hover:bg-stone-200'
            "
            @click="toggleActive(employee)"
          >
            {{ employee.is_active ? 'Active' : 'Inactive' }}
          </button>
          <span
            v-else
            class="rounded-lg bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
          >
            Active
          </span>

          <button
            v-if="isManageable(employee)"
            class="rounded-lg px-2 py-1 text-xs font-medium text-red-500 hover:bg-red-50"
            @click="remove(employee)"
          >
            Remove
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
