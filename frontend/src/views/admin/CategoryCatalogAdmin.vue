<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, Pencil, Trash2, Eye, EyeOff, RefreshCw } from 'lucide-vue-next'
import { LOCALES } from '@/i18n'
import { getCategoryPresets, saveCategoryPreset, deleteCategoryPreset } from '@/services/admin'

/**
 * The platform's category catalog. Restaurants pick their menu sections from
 * it, so every option carries its name (and optional description) in every
 * language — that's what keeps restaurant menus translated.
 */

const { t, locale } = useI18n()

const presets = ref([])
const loading = ref(true)
const error = ref(null)
const busyId = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    presets.value = await getCategoryPresets()
  } catch (err) {
    error.value = err?.response?.data?.message || t('catalog.loadError')
  } finally {
    loading.value = false
  }
}

onMounted(load)
// List names come from the API in the current language.
watch(locale, load)

// --- Add / edit modal ---
const editing = ref(null) // null = closed, {} = new, preset = edit
const saving = ref(false)
const formError = ref(null)
const formErrors = ref({})
const form = reactive({ translations: {}, sort_order: 0, is_active: true })

function open(preset = {}) {
  formError.value = null
  formErrors.value = {}
  form.translations = Object.fromEntries(
    LOCALES.map((l) => [
      l.code,
      {
        name: preset.translations?.[l.code]?.name ?? '',
        description: preset.translations?.[l.code]?.description ?? '',
      },
    ]),
  )
  form.sort_order = preset.sort_order ?? (presets.value.at(-1)?.sort_order ?? 0) + 10
  form.is_active = preset.is_active ?? true
  editing.value = preset
}

async function save() {
  saving.value = true
  formError.value = null
  formErrors.value = {}
  try {
    const saved = await saveCategoryPreset(
      { translations: form.translations, sort_order: Number(form.sort_order) || 0, is_active: form.is_active },
      editing.value.id,
    )
    if (editing.value.id) Object.assign(editing.value, saved)
    else presets.value.push(saved)
    presets.value.sort((a, b) => a.sort_order - b.sort_order || a.id - b.id)
    editing.value = null
  } catch (err) {
    formErrors.value = err?.response?.data?.errors || {}
    formError.value = err?.response?.data?.message || t('catalog.saveError')
  } finally {
    saving.value = false
  }
}

function fieldError(code, field) {
  return formErrors.value[`translations.${code}.${field}`]?.[0]
}

async function toggle(preset) {
  busyId.value = preset.id
  try {
    Object.assign(
      preset,
      await saveCategoryPreset(
        { translations: preset.translations, sort_order: preset.sort_order, is_active: !preset.is_active },
        preset.id,
      ),
    )
  } catch (err) {
    error.value = err?.response?.data?.message || t('catalog.saveError')
  } finally {
    busyId.value = null
  }
}

async function remove(preset) {
  if (!window.confirm(t('catalog.confirmDelete', { name: preset.name }))) return
  busyId.value = preset.id
  try {
    await deleteCategoryPreset(preset.id)
    presets.value = presets.value.filter((p) => p.id !== preset.id)
  } catch (err) {
    error.value = err?.response?.data?.message || t('catalog.deleteError')
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <div>
    <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ t('catalog.title') }}</h1>
        <p class="text-sm text-ink-500">{{ t('catalog.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="flex items-center gap-2 rounded-xl border border-hairline bg-white px-3.5 py-2 text-sm font-medium text-ink-600 transition hover:bg-canvas disabled:opacity-60"
          :disabled="loading"
          @click="load"
        >
          <RefreshCw :size="16" :class="loading && 'animate-spin'" />
          {{ t('admin.refresh') }}
        </button>
        <button
          class="btn-brand flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold"
          @click="open()"
        >
          <Plus :size="16" />
          {{ t('catalog.add') }}
        </button>
      </div>
    </header>

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading && !presets.length" class="text-sm text-ink-400">{{ t('common.loading') }}</p>
    <p v-else-if="!presets.length" class="card p-8 text-center text-sm text-ink-400">{{ t('catalog.empty') }}</p>

    <div v-else class="card divide-y divide-hairline">
      <div
        v-for="p in presets"
        :key="p.id"
        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3"
        :class="busyId === p.id && 'opacity-50'"
      >
        <div class="min-w-0 flex-1">
          <p class="font-medium" :class="p.is_active ? 'text-ink-800' : 'text-ink-400 line-through'">{{ p.name }}</p>
          <p class="truncate text-xs text-ink-400">
            <span v-for="(l, i) in LOCALES" :key="l.code">
              <template v-if="i">· </template>{{ l.short }}: {{ p.translations?.[l.code]?.name || '—' }}
            </span>
          </p>
        </div>
        <span class="text-xs text-ink-400">{{ t('catalog.usedCount', p.categories_count ?? 0) }}</span>
        <span
          class="rounded-full px-2 py-0.5 text-xs font-medium"
          :class="p.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-600'"
        >
          {{ p.is_active ? t('catalog.visible') : t('catalog.hidden') }}
        </span>
        <div class="flex items-center gap-1">
          <button
            class="rounded-lg p-1.5 text-ink-500 hover:bg-canvas"
            :title="p.is_active ? t('catalog.hide') : t('catalog.show')"
            :aria-label="p.is_active ? t('catalog.hide') : t('catalog.show')"
            :disabled="busyId === p.id"
            @click="toggle(p)"
          >
            <component :is="p.is_active ? EyeOff : Eye" :size="16" />
          </button>
          <button
            class="rounded-lg p-1.5 text-ink-500 hover:bg-canvas"
            :title="t('catalog.edit')"
            :aria-label="t('catalog.edit')"
            @click="open(p)"
          >
            <Pencil :size="16" />
          </button>
          <button
            v-if="!p.categories_count"
            class="rounded-lg p-1.5 text-red-500 hover:bg-red-50"
            :title="t('common.delete')"
            :aria-label="t('common.delete')"
            :disabled="busyId === p.id"
            @click="remove(p)"
          >
            <Trash2 :size="16" />
          </button>
        </div>
      </div>
    </div>

    <!-- Add / edit -->
    <div
      v-if="editing"
      class="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/50 p-4 backdrop-blur-[2px]"
      @click.self="editing = null"
    >
      <form class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="save">
        <h2 class="text-lg font-bold text-ink-900">{{ editing.id ? t('catalog.edit') : t('catalog.add') }}</h2>
        <p class="mt-1 text-xs text-ink-400">{{ t('catalog.allLanguagesHint') }}</p>

        <fieldset v-for="l in LOCALES" :key="l.code" class="mt-4 space-y-3 rounded-2xl border border-hairline p-4">
          <legend class="px-1 text-xs font-semibold text-ink-600">{{ l.label }}</legend>
          <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">{{ t('catalog.name') }}</label>
            <input
              v-model="form.translations[l.code].name"
              required
              maxlength="255"
              class="field"
            />
            <p v-if="fieldError(l.code, 'name')" class="mt-1 text-xs text-red-600">{{ fieldError(l.code, 'name') }}</p>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-ink-500">{{ t('catalog.descriptionOptional') }}</label>
            <input
              v-model="form.translations[l.code].description"
              maxlength="1000"
              class="field"
            />
          </div>
        </fieldset>

        <div class="mt-4 flex flex-wrap items-center gap-4">
          <label class="flex items-center gap-2 text-sm text-ink-700">
            {{ t('catalog.order') }}
            <input
              v-model="form.sort_order"
              type="number"
              min="0"
              class="field w-24 py-1.5"
            />
          </label>
          <label class="flex items-center gap-2 text-sm text-ink-700">
            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-brand-500" />
            {{ t('catalog.visible') }}
          </label>
        </div>

        <p v-if="formError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ formError }}</p>

        <div class="mt-5 flex justify-end gap-2">
          <button
            type="button"
            class="btn-ghost rounded-xl px-4 py-2 text-sm font-medium"
            @click="editing = null"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="btn-brand rounded-xl px-5 py-2 text-sm font-semibold disabled:opacity-60"
          >
            {{ saving ? t('menuManager.saving') : t('common.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
