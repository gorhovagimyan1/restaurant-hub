<script setup>
/**
 * The standard heading block at the top of every dashboard screen: title,
 * one-line subtitle, and an optional slot for the screen's actions.
 */
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <header class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">
        {{ title }}
      </h1>
      <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-ink-500">
        <slot name="subtitle">{{ subtitle }}</slot>
      </p>
    </div>

    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>
