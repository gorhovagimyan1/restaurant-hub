<script setup>
import { ref } from 'vue'
import { X } from 'lucide-vue-next'

/**
 * A list of short values edited as chips: type and press Enter (or a comma)
 * to add, × or Backspace to remove. Pasting "a, b, c" adds three. Text left
 * in the box is added on blur, so nothing typed is lost when saving.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  maxLength: { type: Number, default: 100 },
})
const emit = defineEmits(['update:modelValue'])

const draft = ref('')
const input = ref(null)

function add(raw) {
  const values = String(raw)
    .split(',')
    .map((v) => v.trim().slice(0, props.maxLength))
    .filter(Boolean)
  if (!values.length) return
  const existing = new Set(props.modelValue.map((v) => v.toLowerCase()))
  const fresh = values.filter((v) => !existing.has(v.toLowerCase()) && existing.add(v.toLowerCase()))
  if (fresh.length) emit('update:modelValue', [...props.modelValue, ...fresh])
}

function commit() {
  add(draft.value)
  draft.value = ''
}

function onKeydown(event) {
  if (event.key === 'Enter' || event.key === ',') {
    // Enter would otherwise submit the whole form.
    event.preventDefault()
    commit()
  } else if (event.key === 'Backspace' && !draft.value && props.modelValue.length) {
    emit('update:modelValue', props.modelValue.slice(0, -1))
  }
}

function onPaste(event) {
  const text = event.clipboardData?.getData('text') ?? ''
  if (!text.includes(',')) return
  event.preventDefault()
  add(draft.value + text)
  draft.value = ''
}

function removeAt(index) {
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
  input.value?.focus()
}
</script>

<template>
  <div
    class="flex min-h-[2.6rem] w-full cursor-text flex-wrap items-center gap-1.5 rounded-xl border border-hairline-strong bg-white px-2 py-1.5 transition focus-within:border-brand-400 focus-within:ring-[3px] focus-within:ring-brand-500/15"
    @click="input?.focus()"
  >
    <span
      v-for="(tag, i) in modelValue"
      :key="`${tag}-${i}`"
      class="inline-flex items-center gap-1 rounded-full border border-brand-100 bg-brand-50 py-0.5 pl-2.5 pr-1 text-xs font-medium text-brand-800"
    >
      {{ tag }}
      <button
        type="button"
        class="rounded-full p-0.5 text-brand-600/70 transition hover:bg-brand-100 hover:text-brand-800"
        :aria-label="`× ${tag}`"
        @click.stop="removeAt(i)"
      >
        <X :size="12" />
      </button>
    </span>
    <input
      ref="input"
      v-model="draft"
      :placeholder="modelValue.length ? '' : placeholder"
      :maxlength="maxLength"
      class="min-w-[8rem] flex-1 border-0 bg-transparent px-1 py-0.5 text-sm outline-none"
      @keydown="onKeydown"
      @paste="onPaste"
      @blur="commit"
    />
  </div>
</template>
