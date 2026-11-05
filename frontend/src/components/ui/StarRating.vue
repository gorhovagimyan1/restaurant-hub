<script setup>
import { Star } from 'lucide-vue-next'

/**
 * Five stars. Read-only by default (shows `modelValue`, which may be a decimal
 * like 4.6); pass `editable` to let a guest tap a whole-star rating.
 */
const props = defineProps({
  modelValue: { type: Number, default: 0 },
  editable: { type: Boolean, default: false },
  size: { type: Number, default: 14 },
})

const emit = defineEmits(['update:modelValue'])

function fill(star) {
  // 0..1: how much of this star is lit (so 4.6 lights the fifth star 60%).
  return Math.max(0, Math.min(1, props.modelValue - (star - 1)))
}
</script>

<template>
  <span class="inline-flex items-center gap-0.5" :aria-label="`${modelValue} out of 5 stars`">
    <component
      :is="editable ? 'button' : 'span'"
      v-for="star in 5"
      :key="star"
      :type="editable ? 'button' : undefined"
      class="relative inline-block leading-none"
      :class="editable && 'p-0.5 transition active:scale-90'"
      :aria-label="editable ? `${star} star${star > 1 ? 's' : ''}` : undefined"
      @click="editable && emit('update:modelValue', star)"
    >
      <Star :size="size" class="opacity-30" />
      <span class="absolute inset-0 overflow-hidden" :style="{ width: `${fill(star) * 100}%` }">
        <Star :size="size" style="fill: currentColor" />
      </span>
    </component>
  </span>
</template>
