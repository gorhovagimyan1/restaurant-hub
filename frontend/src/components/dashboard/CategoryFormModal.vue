<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { X } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import { getCategoryPresets } from '@/services/dashboard'
import { initText, splitText } from '@/utils/translatable'
import LanguageTabs from '@/components/ui/LanguageTabs.vue'
import ImageUploadField from '@/components/ui/ImageUploadField.vue'
import PrepStationField from '@/components/dashboard/PrepStationField.vue'

const props = defineProps({
  category: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const { t, locale } = useI18n()
const dashboard = useDashboardStore()
const saving = ref(false)
const error = ref(null)

// Categories are normally picked from the platform catalog, which already has
// the name and description in every language. "custom" is the fallback for
// sections the catalog lacks: the owner names those (main language required,
// the others optional).
const CUSTOM = 'custom'
const presets = ref([])
const choice = ref(props.category ? (props.category.category_preset_id ?? CUSTOM) : '')

const selectedPreset = computed(() => presets.value.find((p) => p.id === choice.value) || null)

// Catalog options this restaurant already uses (one each per menu).
const usedPresetIds = computed(
  () =>
    new Set(
      dashboard.categories
        .filter((c) => c.category_preset_id && c.id !== props.category?.id)
        .map((c) => c.category_preset_id),
    ),
)

const mainLocale = computed(() => dashboard.restaurant?.default_language || 'hy')
const lang = ref(mainLocale.value)
const text = reactive(initText(props.category, { name: '', description: '' }, mainLocale.value))

const form = reactive({
  is_active: props.category?.is_active ?? true,
  // Where its products are prepared, and whether they are 18+.
  prep_station: props.category?.prep_station ?? 'kitchen',
  is_alcoholic: props.category?.is_alcoholic ?? false,
})

// Picking a catalog option takes its drink defaults ("Cocktails" → bar, 18+);
// the owner can still change them. Re-showing the saved option keeps theirs.
watch(selectedPreset, (preset) => {
  if (!preset || preset.id === props.category?.category_preset_id) return
  form.prep_station = preset.prep_station ?? 'kitchen'
  form.is_alcoholic = !!preset.is_alcoholic
})

// Photo from the owner's device: uploaded after the category is saved.
// undefined = unchanged, File = new photo, null = remove the current one.
const imageFile = ref(undefined)
const imagePreview = ref(props.category?.image ?? null)

function pickImage(file) {
  imageFile.value = file
  imagePreview.value = URL.createObjectURL(file)
}

function removeImage() {
  imageFile.value = null
  imagePreview.value = null
}

async function loadPresets() {
  try {
    presets.value = await getCategoryPresets()
  } catch {
    // Without the catalog the owner can still add a custom category.
    if (!choice.value) choice.value = CUSTOM
  }
}

onMounted(loadPresets)
// Option names come from the API in the current language.
watch(locale, loadPresets)

function textPayload() {
  if (choice.value === CUSTOM) {
    return { category_preset_id: null, ...splitText(text, mainLocale.value) }
  }
  return { category_preset_id: choice.value }
}

async function submit() {
  error.value = null
  if (!choice.value) {
    error.value = t('catalog.pickRequired')
    return
  }
  if (choice.value === CUSTOM && !text[mainLocale.value].name.trim()) {
    lang.value = mainLocale.value
    error.value = t('menuManager.nameRequired')
    return
  }

  saving.value = true
  try {
    await dashboard.saveCategory(
      {
        ...textPayload(),
        is_active: form.is_active,
        prep_station: form.prep_station,
        is_alcoholic: form.is_alcoholic,
      },
      props.category?.id,
      imageFile.value,
    )
    emit('close')
  } catch (err) {
    // Prefer the specific reason (e.g. a rejected photo) over the summary.
    const errors = err?.response?.data?.errors
    error.value =
      (errors && Object.values(errors)[0]?.[0]) || err?.response?.data?.message || t('menuManager.saveCategoryError')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/50 backdrop-blur-[2px] sm:items-center sm:p-4"
    @click.self="emit('close')"
  >
    <!-- Header and footer stay put; only the fields scroll. -->
    <form
      class="flex max-h-[94vh] w-full max-w-md flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-h-[90vh] sm:rounded-2xl"
      @submit.prevent="submit"
    >
      <header class="flex items-center justify-between gap-3 border-b border-hairline px-5 py-4 sm:px-6">
        <h2 class="text-lg font-semibold tracking-tight text-ink-900">
          {{ category ? t('menuManager.editCategory') : t('menuManager.newCategory') }}
        </h2>
        <button
          type="button"
          class="-mr-1.5 rounded-lg p-1.5 text-ink-400 transition hover:bg-canvas hover:text-ink-700"
          :aria-label="t('common.cancel')"
          @click="emit('close')"
        >
          <X :size="18" />
        </button>
      </header>

      <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
        <div>
          <label class="mb-1 block text-xs font-medium text-ink-500">{{ t('catalog.pick') }}</label>
          <select v-model="choice" class="field field-select">
            <option value="" disabled>{{ t('catalog.pickPlaceholder') }}</option>
            <option
              v-for="p in presets"
              :key="p.id"
              :value="p.id"
              :disabled="usedPresetIds.has(p.id)"
            >
              {{ p.name }}{{ usedPresetIds.has(p.id) ? ` — ${t('catalog.alreadyAdded')}` : '' }}
            </option>
            <option :value="CUSTOM">{{ t('catalog.custom') }}</option>
          </select>
        </div>

        <!-- Catalog category: nothing to write, just show what guests will see. -->
        <div v-if="selectedPreset" class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3">
          <p class="text-sm font-semibold text-ink-800">{{ selectedPreset.name }}</p>
          <p v-if="selectedPreset.description" class="mt-0.5 text-xs text-ink-500">
            {{ selectedPreset.description }}
          </p>
          <p class="mt-2 text-[11px] text-brand-700">{{ t('catalog.pickHint') }}</p>
        </div>

        <!-- Custom category: the owner names it, per language. -->
        <section v-else-if="choice === CUSTOM" class="overflow-hidden rounded-2xl border border-hairline">
          <div class="border-b border-hairline bg-canvas/60 px-4 py-3">
            <p class="mb-2 text-xs text-ink-500">{{ t('catalog.customHint') }}</p>
            <LanguageTabs v-model="lang" :main="mainLocale" :text="text" />
          </div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-xs font-medium text-ink-500">{{ t('menuManager.name') }}</label>
              <input v-model="text[lang].name" :required="lang === mainLocale" maxlength="255" class="field" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-ink-500">{{ t('menuManager.description') }}</label>
              <textarea v-model="text[lang].description" rows="2" maxlength="1000" class="field resize-y" />
            </div>
          </div>
        </section>

        <ImageUploadField
          :label="t('menuManager.categoryImage')"
          shape="wide"
          :max-mb="4"
          :src="imagePreview"
          :busy="saving && !!imageFile"
          @select="pickImage"
          @remove="removeImage"
        />

        <PrepStationField v-model:station="form.prep_station" v-model:alcoholic="form.is_alcoholic" />

        <label
          class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm transition"
          :class="form.is_active ? 'border-brand-200 bg-brand-50 text-brand-800' : 'border-hairline-strong text-ink-600'"
        >
          <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-brand-500" />
          {{ t('menuManager.active') }}
        </label>
      </div>

      <!-- Always-visible actions -->
      <footer class="border-t border-hairline bg-white px-5 py-3 sm:px-6">
        <p v-if="error" class="mb-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600" role="alert">{{ error }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-ghost rounded-xl px-4 py-2 text-sm font-medium" @click="emit('close')">
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
      </footer>
    </form>
  </div>
</template>
