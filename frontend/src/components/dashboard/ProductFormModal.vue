<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronDown, X } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import { initText, splitText } from '@/utils/translatable'
import LanguageTabs from '@/components/ui/LanguageTabs.vue'
import ImageUploadField from '@/components/ui/ImageUploadField.vue'
import TagInput from '@/components/ui/TagInput.vue'

const props = defineProps({
  product: { type: Object, default: null },
  categories: { type: Array, required: true },
  defaultCategoryId: { type: [Number, null], default: null },
})
const emit = defineEmits(['close'])

const { t } = useI18n()
const dashboard = useDashboardStore()
const saving = ref(false)
const error = ref(null)

const currency = computed(() => dashboard.restaurant?.currency || 'AMD')

// --- Photo: uploaded after the dish is saved ---
// undefined = unchanged, File = new photo, null = remove the current one.
const imageFile = ref(undefined)
const imagePreview = ref(props.product?.image ?? null)

function pickImage(file) {
  imageFile.value = file
  imagePreview.value = URL.createObjectURL(file)
}

function removeImage() {
  imageFile.value = null
  imagePreview.value = null
}

// --- Text, per language ---
// Name, description and ingredients are written per language; the
// restaurant's default language (Settings) is required and stands in for
// any language left blank.
const mainLocale = computed(() => dashboard.restaurant?.default_language || 'hy')
const lang = ref(mainLocale.value)
const text = reactive(
  initText(props.product, { name: '', description: '', ingredients: [] }, mainLocale.value),
)

// --- Everything else ---
const form = reactive({
  category_id: props.product?.category_id ?? props.defaultCategoryId ?? props.categories[0]?.id ?? null,
  price: props.product?.price ?? '',
  preparation_time: props.product?.preparation_time ?? '',
  is_available: props.product?.is_available ?? true,
  is_featured: props.product?.is_featured ?? false,
  stock_quantity: props.product?.stock_quantity ?? '',
  low_stock_threshold: props.product?.low_stock_threshold ?? '',
  daily_stock: props.product?.daily_stock ?? '',
})

const toggles = computed(() => [
  { key: 'is_available', label: t('menuManager.available'), onClass: 'border-brand-200 bg-brand-50/70', trackClass: 'bg-brand-500' },
  { key: 'is_featured', label: t('menuManager.chefsPick'), onClass: 'border-amber-200 bg-amber-50/70', trackClass: 'bg-amber-500' },
])

// Most dishes don't count portions, so stock settings start folded away —
// unless this dish already uses them.
const stockOpen = ref(
  [props.product?.stock_quantity, props.product?.low_stock_threshold, props.product?.daily_stock].some(
    (v) => v !== null && v !== undefined && v !== '',
  ),
)

async function submit() {
  error.value = null
  // The main language's name is required; other tabs hide its input.
  if (!text[mainLocale.value].name.trim()) {
    lang.value = mainLocale.value
    error.value = t('menuManager.nameRequired')
    return
  }
  saving.value = true
  try {
    const payload = {
      category_id: form.category_id,
      ...splitText(text, mainLocale.value),
      price: Number(form.price),
      preparation_time: form.preparation_time ? Number(form.preparation_time) : null,
      is_available: form.is_available,
      is_featured: form.is_featured,
      // Empty = stock not tracked.
      stock_quantity: form.stock_quantity === '' ? null : Number(form.stock_quantity),
      low_stock_threshold: form.low_stock_threshold === '' ? null : Number(form.low_stock_threshold),
      daily_stock: form.daily_stock === '' ? null : Number(form.daily_stock),
    }
    await dashboard.saveProduct(payload, props.product?.id, imageFile.value)
    emit('close')
  } catch (err) {
    // Prefer the specific reason (e.g. a rejected photo) over the summary.
    const errors = err?.response?.data?.errors
    error.value =
      (errors && Object.values(errors)[0]?.[0]) || err?.response?.data?.message || t('menuManager.saveProductError')
  } finally {
    saving.value = false
  }
}

// Same look as every other dashboard form (see `field` in main.css).
const labelClass = 'mb-1 block text-xs font-medium text-ink-500'
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/50 backdrop-blur-[2px] sm:items-center sm:p-4"
    @click.self="emit('close')"
  >
    <!-- Header and footer stay put; only the fields scroll. -->
    <form
      class="flex max-h-[94vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-h-[90vh] sm:rounded-2xl"
      @submit.prevent="submit"
    >
      <header class="flex items-center justify-between gap-3 border-b border-hairline px-5 py-4 sm:px-6">
        <h2 class="text-lg font-semibold tracking-tight text-ink-900">
          {{ product ? t('menuManager.editProduct') : t('menuManager.newProduct') }}
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

      <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
        <!-- Language-independent basics -->
        <div class="grid gap-5 sm:grid-cols-[minmax(0,15rem)_minmax(0,1fr)]">
          <ImageUploadField
            :label="t('menuManager.photo')"
            shape="wide"
            :max-mb="4"
            :src="imagePreview"
            :busy="saving && !!imageFile"
            @select="pickImage"
            @remove="removeImage"
          />

          <div class="space-y-3">
            <div>
              <label :class="labelClass">{{ t('menuManager.category') }}</label>
              <select v-model="form.category_id" class="field field-select">
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <!-- Inputs align on the bottom even when a long label wraps. -->
            <div class="grid grid-cols-2 items-end gap-3">
              <div>
                <label :class="labelClass">{{ t('menuManager.price') }} ({{ currency }})</label>
                <input v-model="form.price" type="number" min="0" step="1" required inputmode="numeric" class="field" />
              </div>
              <div>
                <label :class="labelClass">{{ t('menuManager.prepTime') }}</label>
                <input v-model="form.preparation_time" type="number" min="0" inputmode="numeric" class="field" />
              </div>
            </div>
          </div>
        </div>

        <!-- On/off settings as switches, side by side under the basics. -->
        <div class="grid gap-2 sm:grid-cols-2">
          <label
            v-for="toggle in toggles"
            :key="toggle.key"
            class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 transition"
            :class="form[toggle.key] ? toggle.onClass : 'border-hairline-strong bg-white'"
          >
            <span class="min-w-0 text-sm font-medium" :class="form[toggle.key] ? 'text-ink-900' : 'text-ink-600'">
              {{ toggle.label }}
            </span>
            <input v-model="form[toggle.key]" type="checkbox" class="peer sr-only" />
            <span
              class="relative h-5 w-9 shrink-0 rounded-full bg-ink-300/70 transition peer-focus-visible:ring-[3px] peer-focus-visible:ring-brand-500/25"
              :class="form[toggle.key] && toggle.trackClass"
              aria-hidden="true"
            >
              <span
                class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform"
                :class="form[toggle.key] && 'translate-x-4'"
              />
            </span>
          </label>
        </div>

        <!-- Everything guests read, per language: one tab switches all three fields. -->
        <section class="overflow-hidden rounded-2xl border border-hairline">
          <div class="border-b border-hairline bg-canvas/60 px-4 py-3">
            <LanguageTabs v-model="lang" :main="mainLocale" :text="text" />
          </div>
          <div class="space-y-4 p-4">
            <div>
              <label :class="labelClass">{{ t('menuManager.name') }}</label>
              <input v-model="text[lang].name" :required="lang === mainLocale" maxlength="255" class="field" />
            </div>
            <div>
              <label :class="labelClass">{{ t('menuManager.description') }}</label>
              <textarea v-model="text[lang].description" rows="3" maxlength="2000" class="field resize-y" />
            </div>
            <div>
              <label :class="labelClass">{{ t('menuManager.ingredients') }}</label>
              <TagInput v-model="text[lang].ingredients" :placeholder="t('menuManager.ingredientsPlaceholder')" />
              <p class="mt-1.5 text-[11px] text-ink-400">{{ t('menuManager.ingredientsHint') }}</p>
            </div>
          </div>
        </section>

        <!-- Stock: optional, folded away -->
        <section class="overflow-hidden rounded-2xl border border-hairline">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-canvas/60"
            :aria-expanded="stockOpen"
            @click="stockOpen = !stockOpen"
          >
            <span class="min-w-0">
              <span class="block text-sm font-semibold text-ink-800">{{ t('menuManager.stockTracking') }}</span>
              <span class="block text-xs text-ink-400">{{ t('menuManager.stockTrackingHint') }}</span>
            </span>
            <ChevronDown :size="18" class="shrink-0 text-ink-400 transition-transform" :class="stockOpen && 'rotate-180'" />
          </button>

          <div v-show="stockOpen" class="grid gap-4 border-t border-hairline p-4 sm:grid-cols-2">
            <div>
              <label :class="labelClass">{{ t('menuManager.stockPortions') }}</label>
              <input
                v-model="form.stock_quantity"
                type="number"
                min="0"
                inputmode="numeric"
                :placeholder="t('menuManager.notTracked')"
                class="field"
              />
            </div>
            <div>
              <label :class="labelClass">{{ t('menuManager.lowStockAt') }}</label>
              <input
                v-model="form.low_stock_threshold"
                type="number"
                min="0"
                inputmode="numeric"
                :placeholder="t('menuManager.defaultFive')"
                class="field"
              />
            </div>
            <div class="sm:col-span-2">
              <label :class="labelClass">{{ t('menuManager.freshDaily') }}</label>
              <input
                v-model="form.daily_stock"
                type="number"
                min="1"
                inputmode="numeric"
                :placeholder="t('menuManager.noDailyRefill')"
                class="field"
              />
              <p class="mt-1.5 text-[11px] text-ink-400">{{ t('menuManager.freshDailyHelp') }}</p>
            </div>
            <p class="rounded-xl bg-canvas px-3 py-2.5 text-[11px] leading-relaxed text-ink-500 sm:col-span-2">
              <strong class="text-ink-700">{{ t('menuManager.stockWord') }}</strong>{{ t('menuManager.stockHelp1') }}
              <br />
              <strong class="text-ink-700">{{ t('menuManager.lowStockWord') }}</strong>{{ t('menuManager.stockHelp2') }}
            </p>
          </div>
        </section>
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
