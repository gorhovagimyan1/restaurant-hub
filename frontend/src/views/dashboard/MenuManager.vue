<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { Plus, Pencil, Trash2, Star, Boxes } from 'lucide-vue-next'
import { useDashboardStore } from '@/stores/dashboard'
import { formatPrice } from '@/utils/format'
import AppImage from '@/components/ui/AppImage.vue'
import CategoryFormModal from '@/components/dashboard/CategoryFormModal.vue'
import ProductFormModal from '@/components/dashboard/ProductFormModal.vue'
import StockModal from '@/components/dashboard/StockModal.vue'

const { t } = useI18n()
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
const stockModal = ref(false)

onMounted(() => {
  if (!categories.value.length) dashboard.init()
})

function currency() {
  return restaurant.value?.currency || 'AMD'
}

async function confirmDeleteCategory(category) {
  if (confirm(t('menuManager.confirmDeleteCategory', { name: category.name }))) {
    await dashboard.removeCategory(category.id)
  }
}

async function confirmDeleteProduct(product) {
  if (confirm(t('menuManager.confirmDeleteProduct', { name: product.name }))) {
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
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ t('menuManager.title') }}</h1>
        <p class="text-sm text-ink-500">{{ t('menuManager.subtitle') }}</p>
      </div>
      <button
        class="flex items-center gap-1.5 rounded-lg border border-hairline-strong px-3 py-2 text-sm font-semibold text-ink-700 transition hover:bg-canvas"
        @click="stockModal = true"
      >
        <Boxes :size="16" /> {{ t('menuManager.updateStock') }}
      </button>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[280px_1fr]">
      <!-- Categories -->
      <section class="card p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-semibold text-ink-800">{{ t('menuManager.categories') }}</h2>
          <button
            class="flex items-center gap-1 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-600"
            @click="categoryModal = { category: null }"
          >
            <Plus :size="14" /> {{ t('menuManager.new') }}
          </button>
        </div>
        <ul class="space-y-1">
          <li
            v-for="category in categories"
            :key="category.id"
            class="group flex items-center justify-between rounded-lg border-l-4 px-3 py-2 text-sm transition"
            :class="
              selectedCategoryId === category.id
                ? 'border-brand-500 bg-brand-100 font-semibold text-brand-900'
                : 'border-transparent text-ink-700 hover:bg-canvas'
            "
          >
            <button class="flex-1 truncate text-left" @click="dashboard.selectCategory(category.id)">
              <span class="font-medium">{{ category.name }}</span>
              <span class="ml-1 text-xs text-ink-400">({{ category.products_count ?? 0 }})</span>
              <span v-if="!category.is_active" class="ml-1 text-xs text-red-400">{{ t('menuManager.hidden') }}</span>
            </button>
            <span class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100">
              <button class="rounded-md p-1.5 text-ink-500 hover:bg-hairline" :title="t('common.edit')" @click="categoryModal = { category }"><Pencil :size="14" /></button>
              <button class="rounded-md p-1.5 text-ink-500 hover:bg-red-100 hover:text-red-500" :title="t('common.delete')" @click="confirmDeleteCategory(category)"><Trash2 :size="14" /></button>
            </span>
          </li>
          <li v-if="!categories.length" class="px-3 py-6 text-center text-sm text-ink-400">
            {{ t('menuManager.noCategories') }}
          </li>
        </ul>
      </section>

      <!-- Products -->
      <section class="card p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-semibold text-ink-800">
            {{ selectedCategory ? selectedCategory.name : t('menuManager.products') }}
          </h2>
          <button
            v-if="selectedCategory"
            class="flex items-center gap-1 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-600"
            @click="productModal = { product: null }"
          >
            <Plus :size="14" /> {{ t('menuManager.newProduct') }}
          </button>
        </div>

        <div v-if="loadingProducts" class="py-10 text-center text-sm text-ink-400">{{ t('common.loading') }}</div>

        <div v-else-if="!selectedCategory" class="py-10 text-center text-sm text-ink-400">
          {{ t('menuManager.selectCategory') }}
        </div>

        <ul v-else class="divide-y divide-hairline">
          <li v-for="product in products" :key="product.id" class="flex flex-wrap items-center gap-x-3 gap-y-2 py-3">
            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-canvas">
              <AppImage :src="product.image" :alt="product.name" class="h-full w-full object-cover" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate font-medium text-ink-800">{{ product.name }}</span>
                <Star v-if="product.is_featured" :size="14" class="shrink-0 fill-brand-500 text-brand-500" :title="t('menuManager.chefsPick')" />
              </div>
              <p class="truncate text-xs text-ink-400">{{ product.description }}</p>
            </div>
            <!-- Price + status + actions: inline on desktop, own line on mobile -->
            <div class="flex w-full items-center gap-2 pl-[68px] sm:w-auto sm:pl-0">
              <span class="shrink-0 text-sm font-semibold text-ink-700">
                {{ formatPrice(product.price, currency()) }}
              </span>
              <span
                v-if="product.rating"
                class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-amber-600"
                :title="t('menuManager.ratingTitle', product.reviews_count)"
              >
                <Star :size="12" class="fill-current" /> {{ product.rating.toFixed(1) }}
                <span class="font-normal text-ink-400">({{ product.reviews_count }})</span>
              </span>
              <span
                v-if="product.stock_status !== 'untracked'"
                class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold"
                :class="{
                  'bg-red-100 text-red-600': product.stock_status === 'out',
                  'bg-amber-100 text-amber-700': product.stock_status === 'low',
                  'bg-hairline text-ink-600': product.stock_status === 'ok',
                }"
                :title="t('menuManager.portionsStaffOnly')"
              >
                {{ product.stock_status === 'out' ? t('menuManager.outOfStock') : t('menuManager.left', { n: product.stock_quantity }) }}
              </span>
              <button
                class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold"
                :class="product.is_available ? 'bg-green-100 text-green-700' : 'bg-hairline text-ink-500'"
                :title="product.is_available ? t('menuManager.availableTitle') : t('menuManager.soldOutTitle')"
                @click="toggleAvailability(product)"
              >
                {{ product.is_available ? t('menuManager.available') : t('menuManager.soldOut') }}
              </button>
              <span class="ml-auto flex shrink-0 items-center gap-1 sm:ml-0">
                <button class="rounded-md p-1.5 text-ink-500 hover:bg-hairline" :title="t('common.edit')" @click="productModal = { product }"><Pencil :size="14" /></button>
                <button class="rounded-md p-1.5 text-ink-500 hover:bg-red-100 hover:text-red-500" :title="t('common.delete')" @click="confirmDeleteProduct(product)"><Trash2 :size="14" /></button>
              </span>
            </div>
          </li>
          <li v-if="!products.length" class="py-10 text-center text-sm text-ink-400">
            {{ t('menuManager.noProducts') }}
          </li>
        </ul>
      </section>
    </div>

    <CategoryFormModal
      v-if="categoryModal"
      :category="categoryModal.category"
      @close="categoryModal = null"
    />
    <StockModal v-if="stockModal" @close="stockModal = false" />
    <ProductFormModal
      v-if="productModal"
      :product="productModal.product"
      :categories="categories"
      :default-category-id="selectedCategoryId"
      @close="productModal = null"
    />
  </div>
</template>
