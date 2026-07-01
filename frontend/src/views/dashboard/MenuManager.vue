<script setup>
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useDashboardStore } from '@/stores/dashboard'
import { formatPrice } from '@/utils/format'
import AppImage from '@/components/ui/AppImage.vue'
import CategoryFormModal from '@/components/dashboard/CategoryFormModal.vue'
import ProductFormModal from '@/components/dashboard/ProductFormModal.vue'

const dashboard = useDashboardStore()
const {
  restaurant,
  categories,
  products,
  selectedCategoryId,
  selectedCategory,
  loadingProducts,
} = storeToRefs(dashboard)

const categoryModal = ref(null) // null | { category }
const productModal = ref(null) // null | { product }

onMounted(() => {
  if (!categories.value.length) dashboard.init()
})

function currency() {
  return restaurant.value?.currency || 'AMD'
}

async function confirmDeleteCategory(category) {
  if (confirm(`Delete category "${category.name}" and all its products?`)) {
    await dashboard.removeCategory(category.id)
  }
}

async function confirmDeleteProduct(product) {
  if (confirm(`Delete "${product.name}"?`)) {
    await dashboard.removeProduct(product.id)
  }
}

async function toggleAvailability(product) {
  await dashboard.saveProduct({ is_available: !product.is_available }, product.id)
}
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-stone-900">Menu Management</h1>
        <p class="text-sm text-stone-500">Manage your categories and dishes.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[280px_1fr]">
      <!-- Categories -->
      <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-semibold text-stone-800">Categories</h2>
          <button
            class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600"
            @click="categoryModal = { category: null }"
          >
            + New
          </button>
        </div>
        <ul class="space-y-1">
          <li
            v-for="category in categories"
            :key="category.id"
            class="group flex items-center justify-between rounded-lg px-3 py-2 text-sm"
            :class="
              selectedCategoryId === category.id ? 'bg-amber-50 text-amber-800' : 'hover:bg-stone-50'
            "
          >
            <button class="flex-1 truncate text-left" @click="dashboard.selectCategory(category.id)">
              <span class="font-medium">{{ category.name }}</span>
              <span class="ml-1 text-xs text-stone-400">({{ category.products_count ?? 0 }})</span>
              <span v-if="!category.is_active" class="ml-1 text-xs text-red-400">hidden</span>
            </button>
            <span class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100">
              <button class="rounded p-1 hover:bg-stone-200" title="Edit" @click="categoryModal = { category }">✎</button>
              <button class="rounded p-1 hover:bg-red-100" title="Delete" @click="confirmDeleteCategory(category)">🗑</button>
            </span>
          </li>
          <li v-if="!categories.length" class="px-3 py-6 text-center text-sm text-stone-400">
            No categories yet.
          </li>
        </ul>
      </section>

      <!-- Products -->
      <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-semibold text-stone-800">
            {{ selectedCategory ? selectedCategory.name : 'Products' }}
          </h2>
          <button
            v-if="selectedCategory"
            class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600"
            @click="productModal = { product: null }"
          >
            + New product
          </button>
        </div>

        <div v-if="loadingProducts" class="py-10 text-center text-sm text-stone-400">Loading…</div>

        <div v-else-if="!selectedCategory" class="py-10 text-center text-sm text-stone-400">
          Select a category to manage its products.
        </div>

        <ul v-else class="divide-y divide-stone-100">
          <li v-for="product in products" :key="product.id" class="flex items-center gap-3 py-3">
            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-stone-100">
              <AppImage :src="product.image" :alt="product.name" class="h-full w-full object-cover" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate font-medium text-stone-800">{{ product.name }}</span>
                <span v-if="product.is_featured" class="text-amber-500" title="Chef's pick">★</span>
              </div>
              <p class="truncate text-xs text-stone-400">{{ product.description }}</p>
            </div>
            <span class="shrink-0 text-sm font-semibold text-stone-700">
              {{ formatPrice(product.price, currency()) }}
            </span>
            <button
              class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold"
              :class="product.is_available ? 'bg-green-100 text-green-700' : 'bg-stone-200 text-stone-500'"
              :title="product.is_available ? 'Available — click to hide' : 'Sold out — click to enable'"
              @click="toggleAvailability(product)"
            >
              {{ product.is_available ? 'Available' : 'Sold out' }}
            </button>
            <span class="flex shrink-0 items-center gap-1">
              <button class="rounded p-1 hover:bg-stone-200" title="Edit" @click="productModal = { product }">✎</button>
              <button class="rounded p-1 hover:bg-red-100" title="Delete" @click="confirmDeleteProduct(product)">🗑</button>
            </span>
          </li>
          <li v-if="!products.length" class="py-10 text-center text-sm text-stone-400">
            No products in this category yet.
          </li>
        </ul>
      </section>
    </div>

    <CategoryFormModal
      v-if="categoryModal"
      :category="categoryModal.category"
      @close="categoryModal = null"
    />
    <ProductFormModal
      v-if="productModal"
      :product="productModal.product"
      :categories="categories"
      :default-category-id="selectedCategoryId"
      @close="productModal = null"
    />
  </div>
</template>
