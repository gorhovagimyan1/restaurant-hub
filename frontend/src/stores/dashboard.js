import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import * as api from '@/services/dashboard'
import i18n from '@/i18n'

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

  // Clear all tenant-scoped state. Called when the authenticated account
  // changes so one owner never sees another's cached menu.
  function reset() {
    restaurant.value = null
    categories.value = []
    products.value = []
    selectedCategoryId.value = null
    loadingCategories.value = false
    loadingProducts.value = false
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

  /**
   * Save a category, then its photo: `image` is a File to upload, `null` to
   * remove the current one, or undefined to leave it as it is.
   */
  async function saveCategory(payload, id, image) {
    const saved = id ? await api.updateCategory(id, payload) : await api.createCategory(payload)
    if (image) await api.uploadCategoryImage(saved.id, image)
    else if (image === null && id) await api.deleteCategoryImage(saved.id)
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

  /**
   * Save a dish, then its photo: `file` is a File to upload, `null` to remove
   * the photo, or undefined to leave it as it is.
   */
  async function saveProduct(payload, id, file) {
    const product = id ? await api.updateProduct(id, payload) : await api.createProduct(payload)
    if (file) await api.uploadProductImage(product.id, file)
    else if (file === null && id) {
      for (const image of product.images ?? []) await api.deleteProductImage(image.id)
    }
    await loadProducts()
    await loadCategories()
    return product
  }

  async function saveStock(items) {
    await api.updateStock(items)
    await loadProducts()
  }

  async function removeProduct(id) {
    await api.deleteProduct(id)
    await loadProducts()
    await loadCategories()
  }

  // Category and dish names come from the API in the current language, so a
  // language switch re-fetches whatever menu is already loaded.
  watch(
    () => i18n.global.locale.value,
    async () => {
      if (!restaurant.value) return
      categories.value = await api.getCategories()
      if (selectedCategoryId.value) products.value = await api.getProducts(selectedCategoryId.value)
    },
  )

  return {
    restaurant,
    categories,
    products,
    selectedCategoryId,
    selectedCategory,
    loadingCategories,
    loadingProducts,
    init,
    reset,
    loadCategories,
    selectCategory,
    loadProducts,
    saveCategory,
    removeCategory,
    saveProduct,
    removeProduct,
    saveStock,
  }
})
