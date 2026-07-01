<script setup>
import { ref, reactive } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import AppImage from '@/components/ui/AppImage.vue'

const props = defineProps({
  product: { type: Object, default: null },
  categories: { type: Array, required: true },
  defaultCategoryId: { type: [Number, null], default: null },
})
const emit = defineEmits(['close'])

const dashboard = useDashboardStore()
const saving = ref(false)
const error = ref(null)

const file = ref(null)
const preview = ref(props.product?.image ?? null)

const form = reactive({
  name: props.product?.name ?? '',
  category_id: props.product?.category_id ?? props.defaultCategoryId ?? props.categories[0]?.id ?? null,
  price: props.product?.price ?? '',
  description: props.product?.description ?? '',
  ingredients: (props.product?.ingredients ?? []).join(', '),
  preparation_time: props.product?.preparation_time ?? '',
  is_available: props.product?.is_available ?? true,
  is_featured: props.product?.is_featured ?? false,
})

function onFile(event) {
  const selected = event.target.files?.[0]
  if (!selected) return
  file.value = selected
  preview.value = URL.createObjectURL(selected)
}

async function submit() {
  error.value = null
  saving.value = true
  try {
    const payload = {
      category_id: form.category_id,
      name: form.name,
      description: form.description || null,
      ingredients: form.ingredients
        ? form.ingredients.split(',').map((s) => s.trim()).filter(Boolean)
        : [],
      price: Number(form.price),
      preparation_time: form.preparation_time ? Number(form.preparation_time) : null,
      is_available: form.is_available,
      is_featured: form.is_featured,
    }
    await dashboard.saveProduct(payload, props.product?.id, file.value)
    emit('close')
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not save the product.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
    <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <h2 class="text-lg font-bold text-stone-900">
        {{ product ? 'Edit product' : 'New product' }}
      </h2>

      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <!-- Image -->
        <div class="flex items-center gap-4">
          <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-stone-100">
            <AppImage v-if="preview" :src="preview" alt="" class="h-full w-full object-cover" />
          </div>
          <label class="cursor-pointer rounded-lg border border-stone-300 px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-50">
            Upload image
            <input type="file" accept="image/*" class="hidden" @change="onFile" />
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="mb-1 block text-sm font-medium text-stone-700">Name</label>
            <input
              v-model="form.name"
              required
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Category</label>
            <select
              v-model="form.category_id"
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
            >
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Price</label>
            <input
              v-model="form.price"
              type="number"
              min="0"
              step="1"
              required
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
            />
          </div>
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
          <label class="mb-1 block text-sm font-medium text-stone-700">Ingredients</label>
          <input
            v-model="form.ingredients"
            placeholder="Comma separated, e.g. Tomato, Basil, Cheese"
            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Prep time (min)</label>
            <input
              v-model="form.preparation_time"
              type="number"
              min="0"
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500"
            />
          </div>
          <div class="flex flex-col justify-center gap-2 pt-4">
            <label class="flex items-center gap-2 text-sm text-stone-700">
              <input v-model="form.is_available" type="checkbox" class="h-4 w-4" /> Available
            </label>
            <label class="flex items-center gap-2 text-sm text-stone-700">
              <input v-model="form.is_featured" type="checkbox" class="h-4 w-4" /> Chef's pick
            </label>
          </div>
        </div>

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
