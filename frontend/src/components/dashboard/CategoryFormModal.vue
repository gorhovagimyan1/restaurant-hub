<script setup>
import { ref, reactive } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'

const props = defineProps({
  category: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const dashboard = useDashboardStore()
const saving = ref(false)
const error = ref(null)

const form = reactive({
  name: props.category?.name ?? '',
  description: props.category?.description ?? '',
  image: props.category?.image ?? '',
  is_active: props.category?.is_active ?? true,
})

async function submit() {
  error.value = null
  saving.value = true
  try {
    await dashboard.saveCategory(
      {
        name: form.name,
        description: form.description || null,
        image: form.image || null,
        is_active: form.is_active,
      },
      props.category?.id,
    )
    emit('close')
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not save the category.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
      <h2 class="text-lg font-bold text-stone-900">
        {{ category ? 'Edit category' : 'New category' }}
      </h2>

      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Name</label>
          <input
            v-model="form.name"
            required
            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Description</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Image URL (optional)</label>
          <input
            v-model="form.image"
            placeholder="https://…"
            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
          />
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-700">
          <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded" />
          Active (visible to customers)
        </label>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100"
            @click="emit('close')"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-60"
          >
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
