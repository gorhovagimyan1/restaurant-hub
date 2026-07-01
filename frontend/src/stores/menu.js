import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { fetchMenu } from '@/services/menu'

export const useMenuStore = defineStore('menu', () => {
  const restaurant = ref(null)
  const categories = ref([])
  const slug = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Product detail modal state.
  const selectedProduct = ref(null)

  function openProduct(product) {
    selectedProduct.value = product
  }

  function closeProduct() {
    selectedProduct.value = null
  }

  const currency = computed(() => restaurant.value?.currency || 'AMD')

  const totalProducts = computed(() =>
    categories.value.reduce((total, category) => total + category.products.length, 0),
  )

  // Featured products across all categories, tagged with their category name.
  const featured = computed(() =>
    categories.value.flatMap((category) =>
      category.products
        .filter((product) => product.is_featured)
        .map((product) => ({ ...product, category: category.name })),
    ),
  )

  function categoryById(id) {
    return categories.value.find((category) => category.id === id) || null
  }

  async function load(targetSlug, { force = false } = {}) {
    if (!targetSlug) return
    if (!force && slug.value === targetSlug && restaurant.value) return

    loading.value = true
    error.value = null
    try {
      const data = await fetchMenu(targetSlug)
      restaurant.value = data.restaurant
      categories.value = data.categories ?? []
      slug.value = targetSlug
    } catch (err) {
      error.value =
        err?.response?.data?.message || 'We could not load the menu. Please try again.'
      restaurant.value = null
      categories.value = []
      slug.value = null
    } finally {
      loading.value = false
    }
  }

  return {
    restaurant,
    categories,
    slug,
    loading,
    error,
    currency,
    totalProducts,
    featured,
    categoryById,
    load,
    selectedProduct,
    openProduct,
    closeProduct,
  }
})
