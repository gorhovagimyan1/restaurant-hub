<script setup>
import { useI18n } from 'vue-i18n'
import { LOCALES, setLocale } from '@/i18n'

defineProps({
  // 'light' for light backgrounds, 'dark' for dark/photo backgrounds.
  tone: { type: String, default: 'light' },
})

const { locale, t } = useI18n()
</script>

<template>
  <div
    class="inline-flex items-center gap-0.5 rounded-full p-0.5"
    :class="tone === 'dark' ? 'bg-white/15' : 'bg-stone-200/70'"
    role="group"
    :aria-label="t('common.language')"
  >
    <button
      v-for="l in LOCALES"
      :key="l.code"
      type="button"
      :title="l.label"
      :aria-label="l.label"
      :aria-pressed="locale === l.code"
      class="flex h-7 w-7 items-center justify-center rounded-full transition"
      :class="
        locale === l.code
          ? 'bg-white shadow-sm ring-2 ring-amber-400'
          : 'opacity-70 hover:opacity-100'
      "
      @click="setLocale(l.code)"
    >
      <!-- Armenia -->
      <svg
        v-if="l.code === 'hy'"
        viewBox="0 0 24 24"
        class="h-5 w-5 overflow-hidden rounded-full"
        aria-hidden="true"
      >
        <rect width="24" height="8" fill="#D90012" />
        <rect y="8" width="24" height="8" fill="#0033A0" />
        <rect y="16" width="24" height="8" fill="#F2A800" />
      </svg>
      <!-- Russia -->
      <svg
        v-else-if="l.code === 'ru'"
        viewBox="0 0 24 24"
        class="h-5 w-5 overflow-hidden rounded-full"
        aria-hidden="true"
      >
        <rect width="24" height="8" fill="#fff" />
        <rect y="8" width="24" height="8" fill="#0039A6" />
        <rect y="16" width="24" height="8" fill="#D52B1E" />
        <rect width="24" height="24" fill="none" stroke="#0001" />
      </svg>
      <!-- English (UK) -->
      <svg v-else viewBox="0 0 24 24" class="h-5 w-5 overflow-hidden rounded-full" aria-hidden="true">
        <rect width="24" height="24" fill="#012169" />
        <path d="M0 0L24 24M24 0L0 24" stroke="#fff" stroke-width="4.5" />
        <path d="M0 0L24 24M24 0L0 24" stroke="#C8102E" stroke-width="2" />
        <path d="M12 0V24M0 12H24" stroke="#fff" stroke-width="7" />
        <path d="M12 0V24M0 12H24" stroke="#C8102E" stroke-width="4" />
      </svg>
    </button>
  </div>
</template>
