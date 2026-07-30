<script setup>
import { ref, reactive, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import {
  getRestaurant,
  updateRestaurant,
  getSettings,
  updateSettings,
  getBusinessHours,
  updateBusinessHours,
  getSpecialHours,
  updateSpecialHours,
} from '@/services/dashboard'

const auth = useAuthStore()
const dashboard = useDashboardStore()
const { restaurant } = storeToRefs(dashboard)

const canSettings = auth.can('settings.manage')

const loading = ref(true)
const loadError = ref(null)

// --- Profile ---
const profile = reactive({
  name: '',
  description: '',
  phone: '',
  email: '',
  website: '',
  address: '',
  city: '',
  country: '',
  postal_code: '',
  currency: '',
  timezone: '',
})
const slug = ref('')
const profileErrors = ref({})
const profileNotice = ref(null)
const savingProfile = ref(false)

// --- Operational settings ---
const settings = reactive({
  default_language: 'hy',
  tax_percentage: 0,
  service_charge: 0,
  allow_guest_orders: true,
  require_table_selection: false,
  enable_waiter_call: true,
  enable_bill_request: true,
  auto_accept_orders: false,
})
const settingsErrors = ref({})
const settingsNotice = ref(null)
const savingSettings = ref(false)

// --- Business hours ---
const hours = ref([])
const hoursErrors = ref({})
const hoursNotice = ref(null)
const savingHours = ref(false)

function hoursError(index, field) {
  return hoursErrors.value[`hours.${index}.${field}`]?.[0]
}

// --- Special days (holidays) ---
const specialHours = ref([])
const specialNotice = ref(null)
const savingSpecial = ref(false)

// Add/edit happens in a modal; the list itself is read-only.
const showSpecialModal = ref(false)
const editingIndex = ref(null)
const modalError = ref(null)
const draft = reactive({
  date: '',
  label: '',
  is_closed: true,
  open_time: '09:00',
  close_time: '17:00',
})

function openAddSpecial() {
  editingIndex.value = null
  modalError.value = null
  Object.assign(draft, {
    date: '',
    label: '',
    is_closed: true,
    open_time: '09:00',
    close_time: '17:00',
  })
  showSpecialModal.value = true
}

function openEditSpecial(index) {
  const day = specialHours.value[index]
  editingIndex.value = index
  modalError.value = null
  Object.assign(draft, {
    date: day.date,
    label: day.label || '',
    is_closed: day.is_closed,
    open_time: day.open_time || '09:00',
    close_time: day.close_time || '17:00',
  })
  showSpecialModal.value = true
}

function closeSpecialModal() {
  showSpecialModal.value = false
}

function formatSpecialDate(date) {
  return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

async function confirmSpecial() {
  modalError.value = null

  if (!draft.date) {
    modalError.value = 'Please choose a date.'
    return
  }
  const duplicate = specialHours.value.some((d, i) => d.date === draft.date && i !== editingIndex.value)
  if (duplicate) {
    modalError.value = 'That date already has an entry.'
    return
  }
  if (!draft.is_closed && (!draft.open_time || !draft.close_time)) {
    modalError.value = 'Set both opening and closing times, or mark the day closed.'
    return
  }

  const entry = {
    date: draft.date,
    is_closed: draft.is_closed,
    open_time: draft.is_closed ? null : draft.open_time,
    close_time: draft.is_closed ? null : draft.close_time,
    label: draft.label.trim() || null,
  }

  const next = [...specialHours.value]
  if (editingIndex.value === null) {
    next.push(entry)
  } else {
    next.splice(editingIndex.value, 1, entry)
  }
  next.sort((a, b) => a.date.localeCompare(b.date))

  await persistSpecial(next, { fromModal: true })
}

async function removeSpecial(index) {
  await persistSpecial(
    specialHours.value.filter((_, i) => i !== index),
  )
}

const TOGGLES = [
  { key: 'allow_guest_orders', label: 'Allow guest orders', hint: 'Guests can order without an account.' },
  { key: 'require_table_selection', label: 'Require table selection', hint: 'Force a table before ordering.' },
  { key: 'enable_waiter_call', label: 'Enable “call waiter”', hint: 'Show the call-waiter button to guests.' },
  { key: 'enable_bill_request', label: 'Enable “request bill”', hint: 'Show the request-bill button to guests.' },
  { key: 'auto_accept_orders', label: 'Auto-accept orders', hint: 'New orders skip the pending step.' },
]

function fillProfile(data) {
  slug.value = data.slug
  profile.name = data.name ?? ''
  profile.description = data.description ?? ''
  profile.phone = data.phone ?? ''
  profile.email = data.email ?? ''
  profile.website = data.website ?? ''
  profile.address = data.address ?? ''
  profile.city = data.city ?? ''
  profile.country = data.country ?? ''
  profile.postal_code = data.postal_code ?? ''
  profile.currency = data.currency ?? ''
  profile.timezone = data.timezone ?? ''
}

function fillSettings(data) {
  Object.assign(settings, data)
}

onMounted(async () => {
  try {
    fillProfile(await getRestaurant())
    hours.value = await getBusinessHours()
    specialHours.value = await getSpecialHours()
    if (canSettings) fillSettings(await getSettings())
  } catch (err) {
    loadError.value = err?.response?.data?.message || 'Could not load settings.'
  } finally {
    loading.value = false
  }
})

async function saveHours() {
  hoursErrors.value = {}
  hoursNotice.value = null
  savingHours.value = true
  try {
    hours.value = await updateBusinessHours(
      hours.value.map((h) => ({
        day_of_week: h.day_of_week,
        is_closed: h.is_closed,
        open_time: h.is_closed ? null : h.open_time || null,
        close_time: h.is_closed ? null : h.close_time || null,
      })),
    )
    hoursNotice.value = 'Hours saved.'
  } catch (err) {
    hoursErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(hoursErrors.value).length) {
      loadError.value = err?.response?.data?.message || 'Could not save the hours.'
    }
  } finally {
    savingHours.value = false
  }
}

// Persist the whole set (add/edit/remove all use bulk replace). On success the
// server response becomes the new source of truth for the list.
async function persistSpecial(list, { fromModal = false } = {}) {
  specialNotice.value = null
  savingSpecial.value = true
  try {
    specialHours.value = await updateSpecialHours(
      list.map((d) => ({
        date: d.date,
        is_closed: d.is_closed,
        open_time: d.is_closed ? null : d.open_time || null,
        close_time: d.is_closed ? null : d.close_time || null,
        label: d.label?.trim() || null,
      })),
    )
    specialNotice.value = 'Special days saved.'
    if (fromModal) showSpecialModal.value = false
  } catch (err) {
    const message =
      err?.response?.data?.errors
        ? 'Please check the date and times.'
        : err?.response?.data?.message || 'Could not save the special days.'
    if (fromModal) {
      modalError.value = message
    } else {
      loadError.value = message
    }
  } finally {
    savingSpecial.value = false
  }
}

async function saveProfile() {
  profileErrors.value = {}
  profileNotice.value = null
  savingProfile.value = true
  try {
    const updated = await updateRestaurant({ ...profile })
    fillProfile(updated)
    // Keep the sidebar/header name in sync.
    if (restaurant.value) restaurant.value.name = updated.name
    profileNotice.value = 'Profile saved.'
  } catch (err) {
    profileErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(profileErrors.value).length) {
      profileNotice.value = null
      loadError.value = err?.response?.data?.message || 'Could not save the profile.'
    }
  } finally {
    savingProfile.value = false
  }
}

async function saveSettings() {
  settingsErrors.value = {}
  settingsNotice.value = null
  savingSettings.value = true
  try {
    fillSettings(
      await updateSettings({
        ...settings,
        tax_percentage: Number(settings.tax_percentage),
        service_charge: Number(settings.service_charge),
      }),
    )
    settingsNotice.value = 'Settings saved.'
  } catch (err) {
    settingsErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(settingsErrors.value).length) {
      loadError.value = err?.response?.data?.message || 'Could not save the settings.'
    }
  } finally {
    savingSettings.value = false
  }
}

const inputClass =
  'mt-1 w-full field'
</script>

<template>
  <div>
    <header class="mb-5">
      <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">Settings</h1>
      <p class="text-sm text-ink-500">Your restaurant's public profile and ordering options.</p>
    </header>

    <p v-if="loadError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
      {{ loadError }}
    </p>
    <p v-if="loading" class="text-sm text-ink-400">Loading…</p>

    <div v-else class="space-y-6">
      <!-- Profile -->
      <form class="card p-6" @submit.prevent="saveProfile">
        <h2 class="text-lg font-bold text-ink-900">Profile</h2>
        <p class="mt-1 text-xs text-ink-400">
          Shown to customers. URL:
          <span class="font-mono text-ink-500">/r/{{ slug }}</span>
        </p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-ink-500">Name</label>
            <input v-model="profile.name" type="text" :class="inputClass" />
            <p v-if="profileErrors.name" class="mt-1 text-xs text-red-600">{{ profileErrors.name[0] }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-ink-500">Description</label>
            <textarea v-model="profile.description" rows="3" :class="inputClass"></textarea>
            <p v-if="profileErrors.description" class="mt-1 text-xs text-red-600">
              {{ profileErrors.description[0] }}
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">Phone</label>
            <input v-model="profile.phone" type="text" :class="inputClass" />
            <p v-if="profileErrors.phone" class="mt-1 text-xs text-red-600">{{ profileErrors.phone[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">Email</label>
            <input v-model="profile.email" type="email" :class="inputClass" />
            <p v-if="profileErrors.email" class="mt-1 text-xs text-red-600">{{ profileErrors.email[0] }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-ink-500">Website</label>
            <input v-model="profile.website" type="url" placeholder="https://…" :class="inputClass" />
            <p v-if="profileErrors.website" class="mt-1 text-xs text-red-600">
              {{ profileErrors.website[0] }}
            </p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-ink-500">Address</label>
            <input v-model="profile.address" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">City</label>
            <input v-model="profile.city" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">Country</label>
            <input v-model="profile.country" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">Postal code</label>
            <input v-model="profile.postal_code" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">Currency</label>
            <input
              v-model="profile.currency"
              type="text"
              maxlength="3"
              placeholder="AMD"
              :class="inputClass"
              class="uppercase"
            />
            <p v-if="profileErrors.currency" class="mt-1 text-xs text-red-600">
              {{ profileErrors.currency[0] }}
            </p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-ink-500">Timezone</label>
            <input v-model="profile.timezone" type="text" placeholder="Asia/Yerevan" :class="inputClass" />
            <p v-if="profileErrors.timezone" class="mt-1 text-xs text-red-600">
              {{ profileErrors.timezone[0] }}
            </p>
          </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
          <button
            type="submit"
            :disabled="savingProfile"
            class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
          >
            {{ savingProfile ? 'Saving…' : 'Save profile' }}
          </button>
          <span v-if="profileNotice" class="text-sm text-green-700">{{ profileNotice }}</span>
        </div>
      </form>

      <!-- Operational settings -->
      <form
        v-if="canSettings"
        class="card p-6"
        @submit.prevent="saveSettings"
      >
        <h2 class="text-lg font-bold text-ink-900">Ordering &amp; service</h2>
        <p class="mt-1 text-xs text-ink-400">Controls how guests order and what they can request.</p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <label class="block text-xs font-medium text-ink-500">Default language</label>
            <input v-model="settings.default_language" type="text" maxlength="5" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">Tax %</label>
            <input
              v-model="settings.tax_percentage"
              type="number"
              min="0"
              max="100"
              step="0.01"
              :class="inputClass"
            />
            <p v-if="settingsErrors.tax_percentage" class="mt-1 text-xs text-red-600">
              {{ settingsErrors.tax_percentage[0] }}
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-500">Service charge %</label>
            <input
              v-model="settings.service_charge"
              type="number"
              min="0"
              max="100"
              step="0.01"
              :class="inputClass"
            />
            <p v-if="settingsErrors.service_charge" class="mt-1 text-xs text-red-600">
              {{ settingsErrors.service_charge[0] }}
            </p>
          </div>
        </div>

        <ul class="mt-5 divide-y divide-hairline">
          <li v-for="t in TOGGLES" :key="t.key" class="flex items-center justify-between py-3">
            <div class="pr-4">
              <p class="text-sm font-medium text-ink-800">{{ t.label }}</p>
              <p class="text-xs text-ink-400">{{ t.hint }}</p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="settings[t.key]"
              class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
              :class="settings[t.key] ? 'bg-brand-500' : 'bg-hairline-strong'"
              @click="settings[t.key] = !settings[t.key]"
            >
              <span
                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                :class="settings[t.key] ? 'translate-x-5' : 'translate-x-0.5'"
              ></span>
            </button>
          </li>
        </ul>

        <div class="mt-4 flex items-center gap-3">
          <button
            type="submit"
            :disabled="savingSettings"
            class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
          >
            {{ savingSettings ? 'Saving…' : 'Save settings' }}
          </button>
          <span v-if="settingsNotice" class="text-sm text-green-700">{{ settingsNotice }}</span>
        </div>
      </form>

      <!-- Business hours -->
      <form class="card p-6" @submit.prevent="saveHours">
        <h2 class="text-lg font-bold text-ink-900">Opening hours</h2>
        <p class="mt-1 text-xs text-ink-400">Shown to customers on your menu page.</p>

        <ul class="mt-4 divide-y divide-hairline">
          <li
            v-for="(day, i) in hours"
            :key="day.day_of_week"
            class="flex flex-wrap items-center gap-3 py-3"
          >
            <span class="w-24 text-sm font-medium text-ink-800">{{ day.day_label }}</span>

            <label class="flex items-center gap-1.5 text-xs text-ink-500">
              <input v-model="day.is_closed" type="checkbox" class="rounded border-hairline-strong" />
              Closed
            </label>

            <template v-if="!day.is_closed">
              <input
                v-model="day.open_time"
                type="time"
                class="field px-2.5 py-1.5"
              />
              <span class="text-ink-400">–</span>
              <input
                v-model="day.close_time"
                type="time"
                class="field px-2.5 py-1.5"
              />
              <span
                v-if="hoursError(i, 'open_time') || hoursError(i, 'close_time')"
                class="text-xs text-red-600"
              >
                {{ hoursError(i, 'open_time') || hoursError(i, 'close_time') }}
              </span>
            </template>
            <span v-else class="text-sm text-ink-400">Closed all day</span>
          </li>
        </ul>

        <div class="mt-4 flex items-center gap-3">
          <button
            type="submit"
            :disabled="savingHours"
            class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
          >
            {{ savingHours ? 'Saving…' : 'Save hours' }}
          </button>
          <span v-if="hoursNotice" class="text-sm text-green-700">{{ hoursNotice }}</span>
        </div>
      </form>

      <!-- Holidays / special days -->
      <section class="card p-6">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-ink-900">Holidays &amp; special days</h2>
            <p class="mt-1 text-xs text-ink-400">
              Override your weekly hours on specific dates. These win over the schedule above.
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-xl bg-brand-500 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-brand-600"
            @click="openAddSpecial"
          >
            + Add date
          </button>
        </div>

        <p v-if="!specialHours.length" class="mt-4 text-sm text-ink-400">
          No special days set. Add one for a holiday or one-off closure.
        </p>

        <ul v-else class="mt-4 divide-y divide-hairline">
          <li
            v-for="(day, i) in specialHours"
            :key="day.date"
            class="flex items-center gap-3 py-3"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-ink-800">
                {{ formatSpecialDate(day.date) }}
                <span v-if="day.label" class="font-normal text-ink-400">· {{ day.label }}</span>
              </p>
              <p class="text-xs" :class="day.is_closed ? 'text-red-500' : 'text-ink-500'">
                {{ day.is_closed ? 'Closed all day' : `${day.open_time} – ${day.close_time}` }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg px-2 py-1 text-xs font-medium text-ink-500 hover:bg-canvas"
              @click="openEditSpecial(i)"
            >
              Edit
            </button>
            <button
              type="button"
              class="rounded-lg px-2 py-1 text-xs font-medium text-red-500 hover:bg-red-50 disabled:opacity-50"
              :disabled="savingSpecial"
              @click="removeSpecial(i)"
            >
              Remove
            </button>
          </li>
        </ul>

        <span v-if="specialNotice" class="mt-3 block text-sm text-green-700">{{ specialNotice }}</span>
      </section>
    </div>

    <!-- Add / edit special day modal -->
    <div
      v-if="showSpecialModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="closeSpecialModal"
    >
      <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="text-lg font-bold text-ink-900">
          {{ editingIndex === null ? 'Add special day' : 'Edit special day' }}
        </h3>

        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-xs font-medium text-ink-500">Date</label>
            <input
              v-model="draft.date"
              type="date"
              class="mt-1 w-full field"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-500">Label (optional)</label>
            <input
              v-model="draft.label"
              type="text"
              placeholder="e.g. Christmas Day"
              class="mt-1 w-full field"
            />
          </div>

          <label class="flex items-center gap-2 text-sm text-ink-600">
            <input v-model="draft.is_closed" type="checkbox" class="rounded border-hairline-strong" />
            Closed all day
          </label>

          <div v-if="!draft.is_closed" class="flex items-center gap-3">
            <div>
              <label class="block text-xs font-medium text-ink-500">Opens</label>
              <input
                v-model="draft.open_time"
                type="time"
                class="mt-1 field"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-ink-500">Closes</label>
              <input
                v-model="draft.close_time"
                type="time"
                class="mt-1 field"
              />
            </div>
          </div>

          <p v-if="modalError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
            {{ modalError }}
          </p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-xl px-4 py-2 text-sm font-medium text-ink-600 hover:bg-canvas"
            @click="closeSpecialModal"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="savingSpecial"
            class="rounded-xl bg-brand-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
            @click="confirmSpecial"
          >
            {{ savingSpecial ? 'Saving…' : editingIndex === null ? 'Add' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
