<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * "Where is it prepared?" + "Contains alcohol (18+)" for categories and
 * products. A category always has a value; a product may follow its category
 * (null), so pass `inherit` with the category's values to offer that choice.
 */
const props = defineProps({
  // kitchen | bar | ready, or null = same as the category (products only)
  station: { type: String, default: null },
  // true / false, or null = same as the category (products only)
  alcoholic: { type: [Boolean, null], default: null },
  // { station, alcoholic } of the product's category; omit for a category.
  inherit: { type: Object, default: null },
})
const emit = defineEmits(['update:station', 'update:alcoholic'])

const { t } = useI18n()

const STATIONS = ['kitchen', 'bar', 'ready']
const INHERIT = '' // <select> value for "same as category"

const stationLabel = (s) => t(`orders.stations.${s}`)
const stationHint = (s) => t(`orders.stationHints.${s}`)

const stationModel = computed({
  get: () => props.station ?? (props.inherit ? INHERIT : 'kitchen'),
  set: (v) => emit('update:station', v === INHERIT ? null : v),
})

// What actually applies, for the hint under the select.
const effectiveStation = computed(() => props.station ?? props.inherit?.station ?? 'kitchen')

// Products: a three-way choice; categories: a plain checkbox.
const alcoholModel = computed({
  get: () => (props.alcoholic === null ? INHERIT : props.alcoholic ? 'yes' : 'no'),
  set: (v) => emit('update:alcoholic', v === INHERIT ? null : v === 'yes'),
})
</script>

<template>
  <div class="space-y-3 rounded-xl border border-hairline p-3">
    <div>
      <label class="mb-1 block text-xs font-medium text-ink-500">{{ t('orders.stationLabel') }}</label>
      <select v-model="stationModel" class="field field-select">
        <option v-if="inherit" :value="INHERIT">
          {{ t('orders.sameAsCategory', { value: stationLabel(inherit.station ?? 'kitchen') }) }}
        </option>
        <option v-for="s in STATIONS" :key="s" :value="s">{{ stationLabel(s) }}</option>
      </select>
      <p class="mt-1 text-[11px] text-ink-400">{{ stationHint(effectiveStation) }}</p>
    </div>

    <div v-if="inherit">
      <label class="mb-1 block text-xs font-medium text-ink-500">{{ t('orders.alcoholLabel') }}</label>
      <select v-model="alcoholModel" class="field field-select">
        <option :value="INHERIT">
          {{ t('orders.sameAsCategory', { value: inherit.alcoholic ? t('common.yes') : t('common.no') }) }}
        </option>
        <option value="yes">{{ t('common.yes') }}</option>
        <option value="no">{{ t('common.no') }}</option>
      </select>
    </div>
    <label v-else class="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
      <input
        type="checkbox"
        class="h-4 w-4 accent-brand-500"
        :checked="!!alcoholic"
        @change="emit('update:alcoholic', $event.target.checked)"
      />
      {{ t('orders.alcoholLabel') }}
    </label>
  </div>
</template>
