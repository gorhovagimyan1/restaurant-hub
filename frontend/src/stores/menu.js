import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { fetchMenu } from '@/services/menu'
import { DEFAULT_THEME } from '@/utils/menuTheme'
import i18n, { suggestLocale } from '@/i18n'
import { useCartStore } from '@/stores/cart'

export const useMenuStore = defineStore('menu', () => {
  const restaurant = ref(null)
  const categories = ref([])
  // Top-rated dishes (computed server-side from guest reviews).
  const bestChoices = ref([])
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

  // The restaurant's own design for this menu. The API always sends a complete
  // theme; the defaults only cover the window before the menu has loaded.
  const theme = computed(() => ({ ...DEFAULT_THEME, ...restaurant.value?.menu_theme }))

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

  async function load(targetSlug, { force = false, silent = false } = {}) {
    if (!targetSlug) return
    if (!force && slug.value === targetSlug && restaurant.value) return

    // A silent reload keeps the current menu on screen instead of a skeleton.
    if (!silent) loading.value = true
    error.value = null
    try {
      const data = await fetchMenu(targetSlug)
      restaurant.value = data.restaurant
      if (data.restaurant?.default_language) suggestLocale(data.restaurant.default_language)
      categories.value = data.categories ?? []
      bestChoices.value = data.best_choices ?? []
      slug.value = targetSlug
      useCartStore().refreshProducts(categories.value.flatMap((c) => c.products ?? []))
    } catch (err) {
      // A failed background refresh keeps the menu already on screen.
      if (silent) return
      error.value =
        err?.response?.data?.message || i18n.global.t('customer.loadError')
      restaurant.value = null
      categories.value = []
      bestChoices.value = []
      slug.value = null
    } finally {
      loading.value = false
    }
  }

  // Dish names and descriptions come from the API in the visitor's language,
  // so switching language re-fetches the menu.
  watch(
    () => i18n.global.locale.value,
    () => {
      if (slug.value) load(slug.value, { force: true, silent: true })
    },
  )

  return {
    restaurant,
    categories,
    bestChoices,
    slug,
    loading,
    error,
    currency,
    theme,
    totalProducts,
    featured,
    categoryById,
    load,
    selectedProduct,
    openProduct,
    closeProduct,
  }
})
