<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  src: { type: String, default: '' },
  alt: { type: String, default: '' },
})

// Neutral placeholder shown while missing or on load error.
const FALLBACK =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300'%3E%3Crect width='100%25' height='100%25' fill='%23efe9df'/%3E%3Cg fill='none' stroke='%23cbbfa8' stroke-width='6'%3E%3Ccircle cx='200' cy='150' r='52'/%3E%3Cline x1='200' y1='150' x2='200' y2='150'/%3E%3C/g%3E%3C/svg%3E"

const current = ref(props.src || FALLBACK)

watch(
  () => props.src,
  (value) => {
    current.value = value || FALLBACK
  },
)

function onError() {
  if (current.value !== FALLBACK) current.value = FALLBACK
}
</script>

<template>
  <img
    :src="current"
    :alt="alt"
    loading="lazy"
    decoding="async"
    @error="onError"
  />
</template>
