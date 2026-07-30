<script setup>
import { ref, computed } from 'vue'
import { ImagePlus, Trash2, Loader2 } from 'lucide-vue-next'
import AppImage from '@/components/ui/AppImage.vue'

/**
 * Drop-or-browse image field with an inline preview.
 *
 * Uploading is the parent's job — this emits the chosen File and reflects the
 * `busy` / `src` props back, so the parent owns the one source of truth.
 */
const props = defineProps({
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  /** Current image URL, or null when unset. */
  src: { type: String, default: null },
  busy: { type: Boolean, default: false },
  /** `square` for logos, `wide` for cover photos. */
  shape: { type: String, default: 'wide' },
  error: { type: String, default: '' },
})

const emit = defineEmits(['select', 'remove'])

const input = ref(null)
const dragging = ref(false)

const frameClass = computed(() =>
  props.shape === 'square' ? 'aspect-square w-28' : 'aspect-[16/9] w-full max-w-sm',
)

function choose(fileList) {
  const file = fileList?.[0]
  if (file) emit('select', file)
}

function onDrop(event) {
  dragging.value = false
  choose(event.dataTransfer?.files)
}

function onPick(event) {
  choose(event.target.files)
  // Reset so re-picking the same file still fires a change.
  event.target.value = ''
}
</script>

<template>
  <div>
    <div class="flex items-baseline justify-between gap-3">
      <label class="text-xs font-medium text-ink-600">{{ label }}</label>
      <button
        v-if="src && !busy"
        type="button"
        class="flex items-center gap-1 text-[11px] font-medium text-ink-400 transition hover:text-red-600"
        @click="emit('remove')"
      >
        <Trash2 :size="12" /> Remove
      </button>
    </div>

    <div
      class="relative mt-1.5 overflow-hidden rounded-xl border-2 border-dashed transition"
      :class="[
        frameClass,
        dragging ? 'border-brand-500 bg-brand-50/50' : 'border-hairline-strong hover:border-brand-300',
      ]"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <!--
        Contained, not cropped: the owner has to see the whole image to judge
        it. A blurred copy fills the leftover space so odd ratios don't sit in
        empty letterbox bars.
      -->
      <template v-if="src">
        <AppImage
          :src="src"
          alt=""
          aria-hidden="true"
          class="absolute inset-0 h-full w-full scale-125 object-cover blur-xl"
        />
        <AppImage :src="src" :alt="label" class="relative h-full w-full object-contain" />
      </template>

      <button
        type="button"
        class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center transition"
        :class="src ? 'bg-ink-900/0 text-transparent hover:bg-ink-900/55 hover:text-white' : 'text-ink-400 hover:text-ink-600'"
        :disabled="busy"
        @click="input?.click()"
      >
        <template v-if="busy">
          <Loader2 :size="20" class="animate-spin" />
        </template>
        <template v-else>
          <ImagePlus :size="20" />
          <span class="px-3 text-[11px] font-medium leading-tight">
            {{ src ? 'Replace' : 'Click or drop an image' }}
          </span>
        </template>
      </button>

      <!-- The spinner needs a backdrop when it sits over an existing image. -->
      <div
        v-if="busy && src"
        class="pointer-events-none absolute inset-0 grid place-items-center bg-ink-900/50 text-white"
      >
        <Loader2 :size="20" class="animate-spin" />
      </div>

      <input
        ref="input"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="hidden"
        @change="onPick"
      />
    </div>

    <p v-if="error" class="mt-1 text-[11px] text-red-600">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-[11px] text-ink-400">{{ hint }}</p>
  </div>
</template>
