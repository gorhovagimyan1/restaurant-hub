<script setup>
/**
 * A single figure on the overview: label, value, supporting hint and an icon.
 * Renders as a button when `to` is set so the tile navigates.
 */
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  hint: { type: String, default: '' },
  icon: { type: [Object, Function], default: null },
  /** Route name to open on click; omit for a static tile. */
  to: { type: String, default: '' },
  /** The one tile that carries the brand gradient — use sparingly. */
  featured: { type: Boolean, default: false },
})

defineEmits(['open'])
</script>

<template>
  <component
    :is="to ? 'button' : 'div'"
    :type="to ? 'button' : undefined"
    class="relative overflow-hidden rounded-2xl p-5 text-left"
    :class="[
      featured
        ? 'bg-gradient-to-br from-brand-600 via-brand-500 to-accent-500 text-white shadow-[0_10px_30px_-12px_rgba(5,150,105,0.55)]'
        : 'card',
      to && !featured ? 'card-link' : '',
      to && featured ? 'transition hover:brightness-105' : '',
    ]"
    @click="to && $emit('open', to)"
  >
    <div class="flex items-start justify-between gap-3">
      <p class="eyebrow" :class="featured && '!text-white/75'">{{ label }}</p>
      <span
        v-if="icon"
        class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
        :class="featured ? 'bg-white/20 text-white' : 'bg-brand-50 text-brand-600'"
      >
        <component :is="icon" :size="16" />
      </span>
    </div>

    <p
      class="mt-3 text-[1.75rem] font-semibold leading-none tracking-tight tabular-nums"
      :class="featured ? 'text-white' : 'text-ink-900'"
    >
      {{ value }}
    </p>
    <p v-if="hint" class="mt-2 text-xs" :class="featured ? 'text-white/70' : 'text-ink-400'">
      {{ hint }}
    </p>

    <div
      v-if="featured"
      class="pointer-events-none absolute -bottom-10 -right-10 h-32 w-32 rounded-full bg-white/15 blur-2xl"
    ></div>
  </component>
</template>
