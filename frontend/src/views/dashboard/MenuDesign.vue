<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RotateCcw, ExternalLink, Check, Smartphone } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import {
  getMenuTheme,
  updateMenuTheme,
  resetMenuTheme,
  uploadRestaurantImage,
  deleteRestaurantImage,
} from '@/services/dashboard'
import { fetchMenu } from '@/services/menu'
import {
  DEFAULT_THEME,
  THEME_FIELDS,
  FONTS,
  LAYOUTS,
  HERO_STYLES,
  preloadAllThemeFonts,
} from '@/utils/menuTheme'
import PageHeader from '@/components/ui/PageHeader.vue'
import AppCard from '@/components/ui/AppCard.vue'
import ImageUploadField from '@/components/ui/ImageUploadField.vue'
import MenuPreview from '@/components/design/MenuPreview.vue'

const dashboard = useDashboardStore()
const { restaurant } = storeToRefs(dashboard)

const theme = reactive({ ...DEFAULT_THEME })
const presets = ref({})
const saved = ref({ ...DEFAULT_THEME })

const loading = ref(true)
const saving = ref(false)
const resetting = ref(false)
const error = ref(null)
const notice = ref(null)

// Real menu content for the preview, so owners judge the design against their
// own dishes rather than placeholder text.
const previewRestaurant = ref(null)
const previewCategories = ref([])

const dirty = computed(() =>
  ['preset', ...THEME_FIELDS].some((key) => theme[key] !== saved.value[key]),
)

const fontOptions = computed(() =>
  Object.entries(FONTS).map(([value, font]) => ({ value, ...font })),
)

const presetList = computed(() =>
  Object.entries(presets.value).map(([value, preset]) => ({ value, ...preset })),
)

function apply(next) {
  Object.assign(theme, next)
}

/**
 * Adopt a preset wholesale — its palette, typography, corners and layout.
 */
function choosePreset(name) {
  const preset = presets.value[name]
  if (!preset) return
  notice.value = null
  apply({ preset: name, ...Object.fromEntries(THEME_FIELDS.map((k) => [k, preset[k]])) })
}

/**
 * Any hand edit means the theme no longer *is* the preset it came from, so the
 * preset picker stops claiming it does.
 */
function edit(field, value) {
  notice.value = null
  theme[field] = value
  if (theme.preset !== 'custom' && presets.value[theme.preset]?.[field] !== value) {
    theme.preset = 'custom'
  }
}

function hydrate(payload) {
  presets.value = payload.presets || {}
  saved.value = { ...DEFAULT_THEME, ...payload.theme }
  apply(saved.value)
}

async function save() {
  saving.value = true
  error.value = null
  notice.value = null
  try {
    hydrate(await updateMenuTheme({ ...theme }))
    notice.value = 'Design saved — your customer menu is updated.'
  } catch (err) {
    error.value =
      err?.response?.data?.message || 'Could not save the design. Please try again.'
  } finally {
    saving.value = false
  }
}

async function revertToDefault() {
  resetting.value = true
  error.value = null
  notice.value = null
  try {
    hydrate(await resetMenuTheme())
    notice.value = 'Design reset to the default look.'
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not reset the design.'
  } finally {
    resetting.value = false
  }
}

function discard() {
  apply(saved.value)
  notice.value = null
}

onMounted(async () => {
  preloadAllThemeFonts()
  try {
    hydrate(await getMenuTheme())
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load your menu design.'
  } finally {
    loading.value = false
  }
})

// The preview needs the restaurant's public menu; it arrives independently of
// the theme and is optional — a failure here must not block designing.
watch(
  restaurant,
  async (value) => {
    if (!value?.slug || previewRestaurant.value) return
    try {
      const data = await fetchMenu(value.slug)
      previewRestaurant.value = data.restaurant
      previewCategories.value = data.categories || []
    } catch {
      previewRestaurant.value = null
    }
  },
  { immediate: true },
)

const previewInfo = computed(() => previewRestaurant.value || restaurant.value)

// --- Logo & cover photo ---
// Uploads save immediately (they are not part of the theme draft), so the
// preview and the live menu pick them up without touching Save design.
const uploading = ref(null)
const imageError = ref(null)

function applyImages(profile) {
  if (previewRestaurant.value) {
    previewRestaurant.value = {
      ...previewRestaurant.value,
      logo: profile.logo,
      cover_image: profile.cover_image,
    }
  }
  // Keep the dashboard's copy in step for anything else reading it.
  if (restaurant.value) {
    restaurant.value.logo = profile.logo
    restaurant.value.cover_image = profile.cover_image
  }
}

async function pickImage(type, file) {
  uploading.value = type
  imageError.value = null
  try {
    applyImages(await uploadRestaurantImage(type, file))
    notice.value = type === 'logo' ? 'Logo updated.' : 'Cover photo updated.'
  } catch (err) {
    imageError.value =
      err?.response?.data?.errors?.image?.[0] ||
      err?.response?.data?.message ||
      'Could not upload that image.'
  } finally {
    uploading.value = null
  }
}

async function removeImage(type) {
  uploading.value = type
  imageError.value = null
  try {
    applyImages(await deleteRestaurantImage(type))
  } catch (err) {
    imageError.value = err?.response?.data?.message || 'Could not remove that image.'
  } finally {
    uploading.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Menu Design"
      subtitle="Style the menu your guests see when they scan a table QR."
    >
      <template #actions>
        <a
          v-if="restaurant"
          :href="`/r/${restaurant.slug}`"
          target="_blank"
          class="btn-ghost flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium"
        >
          <ExternalLink :size="15" /> Open live menu
        </a>
      </template>
    </PageHeader>

    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading" class="text-sm text-ink-400">Loading your design…</p>

    <div v-else class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <!-- Controls -->
      <div class="space-y-5">
        <!-- Presets -->
        <AppCard title="Start from a look" hint="Pick one, then adjust anything you like.">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <button
              v-for="preset in presetList"
              :key="preset.value"
              type="button"
              class="overflow-hidden rounded-xl border text-left transition"
              :class="
                theme.preset === preset.value
                  ? 'border-brand-500 ring-2 ring-brand-500/20'
                  : 'border-hairline hover:border-hairline-strong'
              "
              @click="choosePreset(preset.value)"
            >
              <!-- A miniature of the theme: page, card and accent together. -->
              <div
                class="flex h-16 items-end gap-1.5 p-2.5"
                :style="{ backgroundColor: preset.surface_color }"
              >
                <span
                  class="h-8 flex-1 rounded"
                  :style="{
                    backgroundColor: preset.card_color,
                    borderRadius: `${Math.min(preset.radius, 10)}px`,
                  }"
                ></span>
                <span
                  class="h-8 w-4 rounded"
                  :style="{
                    backgroundColor: preset.primary_color,
                    borderRadius: `${Math.min(preset.radius, 10)}px`,
                  }"
                ></span>
              </div>
              <div class="flex items-center justify-between px-2.5 py-2">
                <span class="text-xs font-semibold text-ink-800">{{ preset.label }}</span>
                <Check v-if="theme.preset === preset.value" :size="14" class="text-brand-600" />
              </div>
            </button>
          </div>

          <p v-if="theme.preset === 'custom'" class="mt-3 text-xs text-ink-400">
            You're on a custom design. Picking a look above replaces it.
          </p>
        </AppCard>

        <!-- Header & photos -->
        <AppCard title="Header & photos" hint="Your cover photo and logo, and how the header uses them.">
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-[minmax(0,1fr)_9rem]">
            <ImageUploadField
              label="Cover photo"
              hint="Shown behind your name. Wide shots work best — around 1600×900."
              shape="wide"
              :src="previewInfo?.cover_image"
              :busy="uploading === 'cover'"
              :error="imageError && uploading !== 'logo' ? imageError : ''"
              @select="(file) => pickImage('cover', file)"
              @remove="removeImage('cover')"
            />
            <ImageUploadField
              label="Logo"
              hint="Appears in the top bar."
              shape="square"
              :src="previewInfo?.logo"
              :busy="uploading === 'logo'"
              @select="(file) => pickImage('logo', file)"
              @remove="removeImage('logo')"
            />
          </div>

          <div class="mt-5">
            <label class="text-xs font-medium text-ink-600">Menu header</label>
            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <button
                v-for="option in HERO_STYLES"
                :key="option.value"
                type="button"
                class="rounded-xl border px-3 py-2.5 text-left transition"
                :class="
                  theme.hero_style === option.value
                    ? 'border-brand-500 bg-brand-50/50'
                    : 'border-hairline hover:border-hairline-strong'
                "
                @click="edit('hero_style', option.value)"
              >
                <span class="block text-sm font-medium text-ink-800">{{ option.label }}</span>
                <span class="block text-[11px] text-ink-400">{{ option.hint }}</span>
              </button>
            </div>
            <p v-if="theme.hero_style === 'compact'" class="mt-2 text-[11px] text-ink-400">
              The compact header doesn't use the cover photo — only your logo and name.
            </p>
          </div>

          <p class="mt-3 text-[11px] text-ink-400">
            Photos save as soon as you upload them — no need to press Save design.
          </p>
        </AppCard>

        <!-- Colours -->
        <AppCard title="Colours" hint="Text, borders and hover states adapt automatically.">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
              v-for="swatch in [
                { key: 'primary_color', label: 'Brand colour', hint: 'Buttons, prices, highlights' },
                { key: 'surface_color', label: 'Page background', hint: 'Behind everything' },
                { key: 'card_color', label: 'Cards & bars', hint: 'Dish cards, top bar' },
              ]"
              :key="swatch.key"
            >
              <label class="block text-xs font-medium text-ink-600">{{ swatch.label }}</label>
              <div class="mt-1.5 flex items-center gap-2">
                <input
                  :value="theme[swatch.key]"
                  type="color"
                  class="h-9 w-10 shrink-0 cursor-pointer rounded-lg border border-hairline-strong bg-surface p-0.5"
                  :aria-label="swatch.label"
                  @input="edit(swatch.key, $event.target.value)"
                />
                <input
                  :value="theme[swatch.key]"
                  type="text"
                  maxlength="7"
                  spellcheck="false"
                  class="field font-mono text-xs uppercase"
                  @change="edit(swatch.key, $event.target.value.toLowerCase())"
                />
              </div>
              <p class="mt-1 text-[11px] text-ink-400">{{ swatch.hint }}</p>
            </div>
          </div>
        </AppCard>

        <!-- Typography -->
        <AppCard title="Typography">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div v-for="slot in [
              { key: 'heading_font', label: 'Headings' },
              { key: 'body_font', label: 'Body text' },
            ]" :key="slot.key">
              <label class="block text-xs font-medium text-ink-600">{{ slot.label }}</label>
              <div class="mt-1.5 space-y-1.5">
                <button
                  v-for="font in fontOptions"
                  :key="font.value"
                  type="button"
                  class="flex w-full items-center justify-between rounded-xl border px-3 py-2 text-left transition"
                  :class="
                    theme[slot.key] === font.value
                      ? 'border-brand-500 bg-brand-50/50'
                      : 'border-hairline hover:border-hairline-strong'
                  "
                  @click="edit(slot.key, font.value)"
                >
                  <span class="text-sm text-ink-800" :style="{ fontFamily: font.stack }">
                    {{ font.label }}
                  </span>
                  <span class="text-[11px] text-ink-400">{{ font.note }}</span>
                </button>
              </div>
            </div>
          </div>
        </AppCard>

        <!-- Shape & layout -->
        <AppCard title="Shape & layout">
          <div class="space-y-5">
            <div>
              <div class="flex items-baseline justify-between">
                <label class="text-xs font-medium text-ink-600">Corner roundness</label>
                <span class="text-xs tabular-nums text-ink-400">{{ theme.radius }}px</span>
              </div>
              <input
                :value="theme.radius"
                type="range"
                min="0"
                max="32"
                step="1"
                class="mt-2 w-full accent-brand-500"
                @input="edit('radius', Number($event.target.value))"
              />
              <div class="mt-1 flex justify-between text-[11px] text-ink-400">
                <span>Sharp</span><span>Rounded</span>
              </div>
            </div>

            <div>
              <label class="text-xs font-medium text-ink-600">Dish layout</label>
              <div class="mt-1.5 grid grid-cols-2 gap-2">
                <button
                  v-for="option in LAYOUTS"
                  :key="option.value"
                  type="button"
                  class="rounded-xl border px-3 py-2.5 text-left transition"
                  :class="
                    theme.layout === option.value
                      ? 'border-brand-500 bg-brand-50/50'
                      : 'border-hairline hover:border-hairline-strong'
                  "
                  @click="edit('layout', option.value)"
                >
                  <span class="block text-sm font-medium text-ink-800">{{ option.label }}</span>
                  <span class="block text-[11px] text-ink-400">{{ option.hint }}</span>
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-ink-800">Show dish photos</p>
                <p class="text-[11px] text-ink-400">
                  Turn off for a text-only menu — cleaner when photos are inconsistent.
                </p>
              </div>
              <button
                type="button"
                role="switch"
                :aria-checked="theme.show_images"
                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                :class="theme.show_images ? 'bg-brand-500' : 'bg-hairline-strong'"
                @click="edit('show_images', !theme.show_images)"
              >
                <span
                  class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow-sm transition"
                  :class="theme.show_images ? 'translate-x-5' : 'translate-x-0.5'"
                ></span>
              </button>
            </div>

          </div>
        </AppCard>

        <button
          type="button"
          class="flex items-center gap-2 text-xs font-medium text-ink-500 transition hover:text-ink-800 disabled:opacity-60"
          :disabled="resetting"
          @click="revertToDefault"
        >
          <RotateCcw :size="14" />
          {{ resetting ? 'Resetting…' : 'Reset to the default design' }}
        </button>
      </div>

      <!-- Live preview -->
      <div class="xl:sticky xl:top-24 xl:self-start">
        <div class="mb-2.5 flex items-center gap-2 text-xs font-medium text-ink-500">
          <Smartphone :size="14" /> Live preview
        </div>
        <div
          class="mx-auto h-[34rem] w-full max-w-[19rem] overflow-hidden rounded-[1.75rem] border-[6px] border-ink-900 bg-ink-900 shadow-pop"
        >
          <MenuPreview
            :theme="theme"
            :restaurant="previewInfo"
            :categories="previewCategories"
          />
        </div>
        <p class="mt-3 text-center text-[11px] text-ink-400">
          {{
            previewCategories.length
              ? 'Showing your own dishes.'
              : 'Sample dishes — add menu items to preview your own.'
          }}
        </p>
      </div>
    </div>

    <!-- Save bar: only in the way when there is something to save. -->
    <Transition name="savebar">
      <div
        v-if="!loading && (dirty || notice)"
        class="sticky bottom-4 z-20 mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-hairline bg-surface/95 px-4 py-3 shadow-lift backdrop-blur"
      >
        <span v-if="dirty" class="flex-1 text-sm text-ink-600">You have unsaved changes.</span>
        <span v-else class="flex flex-1 items-center gap-2 text-sm font-medium text-brand-700">
          <Check :size="15" /> {{ notice }}
        </span>

        <template v-if="dirty">
          <button
            type="button"
            class="btn-ghost rounded-xl px-3.5 py-2 text-sm font-medium"
            @click="discard"
          >
            Discard
          </button>
          <button
            type="button"
            class="btn-brand rounded-xl px-5 py-2 text-sm font-semibold disabled:opacity-60"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? 'Saving…' : 'Save design' }}
          </button>
        </template>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.savebar-enter-active,
.savebar-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.savebar-enter-from,
.savebar-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
