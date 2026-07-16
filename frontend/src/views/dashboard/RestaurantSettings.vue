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
const specialErrors = ref({})
const specialNotice = ref(null)
const savingSpecial = ref(false)

function specialError(index, field) {
  return specialErrors.value[`special_hours.${index}.${field}`]?.[0]
}

function addSpecialDay() {
  specialHours.value.push({
    date: '',
    is_closed: true,
    open_time: '09:00',
    close_time: '17:00',
    label: '',
  })
}

function removeSpecialDay(index) {
  specialHours.value.splice(index, 1)
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

async function saveSpecial() {
  specialErrors.value = {}
  specialNotice.value = null
  savingSpecial.value = true
  try {
    specialHours.value = await updateSpecialHours(
      specialHours.value.map((d) => ({
        date: d.date,
        is_closed: d.is_closed,
        open_time: d.is_closed ? null : d.open_time || null,
        close_time: d.is_closed ? null : d.close_time || null,
        label: d.label?.trim() || null,
      })),
    )
    specialNotice.value = 'Special days saved.'
  } catch (err) {
    specialErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(specialErrors.value).length) {
      loadError.value = err?.response?.data?.message || 'Could not save the special days.'
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
  'mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100'
</script>

<template>
  <div>
    <header class="mb-5">
      <h1 class="text-2xl font-bold text-stone-900">Settings</h1>
      <p class="text-sm text-stone-500">Your restaurant's public profile and ordering options.</p>
    </header>

    <p v-if="loadError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
      {{ loadError }}
    </p>
    <p v-if="loading" class="text-sm text-stone-400">Loading…</p>

    <div v-else class="space-y-6">
      <!-- Profile -->
      <form class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5" @submit.prevent="saveProfile">
        <h2 class="text-lg font-bold text-stone-900">Profile</h2>
        <p class="mt-1 text-xs text-stone-400">
          Shown to customers. URL:
          <span class="font-mono text-stone-500">/r/{{ slug }}</span>
        </p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-stone-500">Name</label>
            <input v-model="profile.name" type="text" :class="inputClass" />
            <p v-if="profileErrors.name" class="mt-1 text-xs text-red-600">{{ profileErrors.name[0] }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-stone-500">Description</label>
            <textarea v-model="profile.description" rows="3" :class="inputClass"></textarea>
            <p v-if="profileErrors.description" class="mt-1 text-xs text-red-600">
              {{ profileErrors.description[0] }}
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium text-stone-500">Phone</label>
            <input v-model="profile.phone" type="text" :class="inputClass" />
            <p v-if="profileErrors.phone" class="mt-1 text-xs text-red-600">{{ profileErrors.phone[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-stone-500">Email</label>
            <input v-model="profile.email" type="email" :class="inputClass" />
            <p v-if="profileErrors.email" class="mt-1 text-xs text-red-600">{{ profileErrors.email[0] }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-stone-500">Website</label>
            <input v-model="profile.website" type="url" placeholder="https://…" :class="inputClass" />
            <p v-if="profileErrors.website" class="mt-1 text-xs text-red-600">
              {{ profileErrors.website[0] }}
            </p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-stone-500">Address</label>
            <input v-model="profile.address" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-stone-500">City</label>
            <input v-model="profile.city" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-stone-500">Country</label>
            <input v-model="profile.country" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-stone-500">Postal code</label>
            <input v-model="profile.postal_code" type="text" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-stone-500">Currency</label>
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
            <label class="block text-xs font-medium text-stone-500">Timezone</label>
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
            class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
          >
            {{ savingProfile ? 'Saving…' : 'Save profile' }}
          </button>
          <span v-if="profileNotice" class="text-sm text-green-700">{{ profileNotice }}</span>
        </div>
      </form>

      <!-- Operational settings -->
      <form
        v-if="canSettings"
        class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5"
        @submit.prevent="saveSettings"
      >
        <h2 class="text-lg font-bold text-stone-900">Ordering &amp; service</h2>
        <p class="mt-1 text-xs text-stone-400">Controls how guests order and what they can request.</p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <label class="block text-xs font-medium text-stone-500">Default language</label>
            <input v-model="settings.default_language" type="text" maxlength="5" :class="inputClass" />
          </div>
          <div>
            <label class="block text-xs font-medium text-stone-500">Tax %</label>
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
            <label class="block text-xs font-medium text-stone-500">Service charge %</label>
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

        <ul class="mt-5 divide-y divide-stone-100">
          <li v-for="t in TOGGLES" :key="t.key" class="flex items-center justify-between py-3">
            <div class="pr-4">
              <p class="text-sm font-medium text-stone-800">{{ t.label }}</p>
              <p class="text-xs text-stone-400">{{ t.hint }}</p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="settings[t.key]"
              class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
              :class="settings[t.key] ? 'bg-amber-500' : 'bg-stone-300'"
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
            class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
          >
            {{ savingSettings ? 'Saving…' : 'Save settings' }}
          </button>
          <span v-if="settingsNotice" class="text-sm text-green-700">{{ settingsNotice }}</span>
        </div>
      </form>

      <!-- Business hours -->
      <form class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5" @submit.prevent="saveHours">
        <h2 class="text-lg font-bold text-stone-900">Opening hours</h2>
        <p class="mt-1 text-xs text-stone-400">Shown to customers on your menu page.</p>

        <ul class="mt-4 divide-y divide-stone-100">
          <li
            v-for="(day, i) in hours"
            :key="day.day_of_week"
            class="flex flex-wrap items-center gap-3 py-3"
          >
            <span class="w-24 text-sm font-medium text-stone-800">{{ day.day_label }}</span>

            <label class="flex items-center gap-1.5 text-xs text-stone-500">
              <input v-model="day.is_closed" type="checkbox" class="rounded border-stone-300" />
              Closed
            </label>

            <template v-if="!day.is_closed">
              <input
                v-model="day.open_time"
                type="time"
                class="rounded-lg border border-stone-200 px-2.5 py-1.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
              <span class="text-stone-400">–</span>
              <input
                v-model="day.close_time"
                type="time"
                class="rounded-lg border border-stone-200 px-2.5 py-1.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
              <span
                v-if="hoursError(i, 'open_time') || hoursError(i, 'close_time')"
                class="text-xs text-red-600"
              >
                {{ hoursError(i, 'open_time') || hoursError(i, 'close_time') }}
              </span>
            </template>
            <span v-else class="text-sm text-stone-400">Closed all day</span>
          </li>
        </ul>

        <div class="mt-4 flex items-center gap-3">
          <button
            type="submit"
            :disabled="savingHours"
            class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
          >
            {{ savingHours ? 'Saving…' : 'Save hours' }}
          </button>
          <span v-if="hoursNotice" class="text-sm text-green-700">{{ hoursNotice }}</span>
        </div>
      </form>

      <!-- Holidays / special days -->
      <form class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5" @submit.prevent="saveSpecial">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-stone-900">Holidays &amp; special days</h2>
            <p class="mt-1 text-xs text-stone-400">
              Override your weekly hours on specific dates. These win over the schedule above.
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-xl border border-stone-200 px-3 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100"
            @click="addSpecialDay"
          >
            + Add date
          </button>
        </div>

        <p v-if="!specialHours.length" class="mt-4 text-sm text-stone-400">
          No special days set. Add one for a holiday or one-off closure.
        </p>

        <ul v-else class="mt-4 space-y-3">
          <li
            v-for="(day, i) in specialHours"
            :key="i"
            class="rounded-xl border border-stone-100 p-3"
          >
            <div class="flex flex-wrap items-center gap-3">
              <div>
                <input
                  v-model="day.date"
                  type="date"
                  class="rounded-lg border border-stone-200 px-2.5 py-1.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
                />
                <p v-if="specialError(i, 'date')" class="mt-1 text-xs text-red-600">
                  {{ specialError(i, 'date') }}
                </p>
              </div>

              <input
                v-model="day.label"
                type="text"
                placeholder="Label (e.g. Christmas)"
                class="min-w-40 flex-1 rounded-lg border border-stone-200 px-2.5 py-1.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />

              <label class="flex items-center gap-1.5 text-xs text-stone-500">
                <input v-model="day.is_closed" type="checkbox" class="rounded border-stone-300" />
                Closed
              </label>

              <button
                type="button"
                class="ml-auto rounded-lg px-2 py-1 text-xs font-medium text-red-500 hover:bg-red-50"
                @click="removeSpecialDay(i)"
              >
                Remove
              </button>
            </div>

            <div v-if="!day.is_closed" class="mt-2 flex flex-wrap items-center gap-3">
              <input
                v-model="day.open_time"
                type="time"
                class="rounded-lg border border-stone-200 px-2.5 py-1.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
              <span class="text-stone-400">–</span>
              <input
                v-model="day.close_time"
                type="time"
                class="rounded-lg border border-stone-200 px-2.5 py-1.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"
              />
              <span
                v-if="specialError(i, 'open_time') || specialError(i, 'close_time')"
                class="text-xs text-red-600"
              >
                {{ specialError(i, 'open_time') || specialError(i, 'close_time') }}
              </span>
            </div>
          </li>
        </ul>

        <div class="mt-4 flex items-center gap-3">
          <button
            type="submit"
            :disabled="savingSpecial"
            class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
          >
            {{ savingSpecial ? 'Saving…' : 'Save special days' }}
          </button>
          <span v-if="specialNotice" class="text-sm text-green-700">{{ specialNotice }}</span>
        </div>
      </form>
    </div>
  </div>
</template>
