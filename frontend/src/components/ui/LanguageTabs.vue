<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { orderedLocales, hasText } from '@/utils/translatable'

const props = defineProps({
  // The language being edited.
  modelValue: { type: String, required: true },
  // The restaurant's default language: required, and the fallback for the rest.
  main: { type: String, required: true },
  // Per-language field values, to mark which languages are filled in.
  text: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const locales = computed(() => orderedLocales(props.main))
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center gap-1 rounded-lg bg-canvas p-1" role="tablist">
      <button
        v-for="l in locales"
        :key="l.code"
        type="button"
        role="tab"
        :aria-selected="modelValue === l.code"
        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition"
        :class="modelValue === l.code ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-800'"
        @click="emit('update:modelValue', l.code)"
      >
        <span
          class="h-1.5 w-1.5 rounded-full"
          :class="hasText(text[l.code]) ? 'bg-brand-500' : 'bg-ink-200'"
          aria-hidden="true"
        />
        {{ l.label }}
        <span v-if="l.code === main" class="font-normal text-ink-400">· {{ t('ui.mainLanguage') }}</span>
      </button>
    </div>
    <p class="mt-1.5 text-xs text-ink-400">
      {{ modelValue === main ? t('ui.mainLanguageHint') : t('ui.otherLanguageHint') }}
    </p>
  </div>
</template>
