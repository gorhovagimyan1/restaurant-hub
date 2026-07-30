<script setup>
/**
 * A dashboard panel. Pass a title (with optional action slot) for the standard
 * header, or leave it off and lay the body out yourself.
 */
defineProps({
  title: { type: String, default: '' },
  hint: { type: String, default: '' },
  /** Drop the built-in padding when the body draws edge-to-edge (tables, lists). */
  flush: { type: Boolean, default: false },
})
</script>

<template>
  <section class="card">
    <div
      v-if="title || $slots.header || $slots.action"
      class="flex items-start justify-between gap-3 px-5 pt-5"
      :class="flush ? 'pb-4' : 'pb-0'"
    >
      <slot name="header">
        <div class="min-w-0">
          <h2 class="font-semibold text-ink-900">{{ title }}</h2>
          <p v-if="hint" class="mt-0.5 text-xs text-ink-400">{{ hint }}</p>
        </div>
      </slot>
      <div v-if="$slots.action" class="shrink-0"><slot name="action" /></div>
    </div>

    <div :class="flush ? '' : 'p-5'">
      <slot />
    </div>
  </section>
</template>
