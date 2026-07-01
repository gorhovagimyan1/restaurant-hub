import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import * as api from '@/services/dashboard'

export const useDashboardStore = defineStore('dashboard', () => {
  const restaurant = ref(null)
  const categories = ref([])
  const products = ref([])
  const selectedCategoryId = ref(null)
  const loadingCategories = ref(false)
  const loadingProducts = ref(false)

  const selectedCategory = computed(
    () => categories.value.find((c) => c.id === selectedCategoryId.value) || null,
  )

  async function init() {
    restaurant.value = await api.getRestaurant()
    await loadCategories()
    if (!selectedCategoryId.value && categories.value.length) {
      await selectCategory(categories.value[0].id)
    }
  }

  async function loadCategories() {
    loadingCategories.value = true
    try {
      categories.value = await api.getCategories()
    } finally {
      loadingCategories.value = false
    }
  }

  async function selectCategory(id) {
    selectedCategoryId.value = id
    await loadProducts()
  }

  async function loadProducts() {
    if (!selectedCategoryId.value) {
      products.value = []
      return
    }
    loadingProducts.value = true
    try {
      products.value = await api.getProducts(selectedCategoryId.value)
    } finally {
      loadingProducts.value = false
    }
  }

  async function saveCategory(payload, id) {
    const saved = id ? await api.updateCategory(id, payload) : await api.createCategory(payload)
    await loadCategories()
    if (!id) await selectCategory(saved.id)
    return saved
  }

  async function removeCategory(id) {
    await api.deleteCategory(id)
    if (selectedCategoryId.value === id) selectedCategoryId.value = null
    await loadCategories()
    if (!selectedCategoryId.value && categories.value.length) {
      await selectCategory(categories.value[0].id)
    } else {
      await loadProducts()
    }
  }

  async function saveProduct(payload, id, file) {
    const product = id ? await api.updateProduct(id, payload) : await api.createProduct(payload)
    if (file) await api.uploadProductImage(product.id, file)
    await loadProducts()
    await loadCategories()
    return product
  }

  async function removeProduct(id) {
    await api.deleteProduct(id)
    await loadProducts()
    await loadCategories()
  }

  return {
    restaurant,
    categories,
    products,
    selectedCategoryId,
    selectedCategory,
    loadingCategories,
    loadingProducts,
    init,
    loadCategories,
    selectCategory,
    loadProducts,
    saveCategory,
    removeCategory,
    saveProduct,
    removeProduct,
  }
})
