import http from './http'

// All endpoints are scoped server-side to the authenticated user's restaurant.

export async function getRestaurant() {
  const { data } = await http.get('/dashboard/restaurant')
  return data.data
}

export async function getCategories() {
  const { data } = await http.get('/dashboard/categories')
  return data.data
}

export async function createCategory(payload) {
  const { data } = await http.post('/dashboard/categories', payload)
  return data.data
}

export async function updateCategory(id, payload) {
  const { data } = await http.put(`/dashboard/categories/${id}`, payload)
  return data.data
}

export async function deleteCategory(id) {
  await http.delete(`/dashboard/categories/${id}`)
}

export async function getProducts(categoryId) {
  const { data } = await http.get('/dashboard/products', {
    params: categoryId ? { category_id: categoryId } : {},
  })
  return data.data
}

export async function createProduct(payload) {
  const { data } = await http.post('/dashboard/products', payload)
  return data.data
}

export async function updateProduct(id, payload) {
  const { data } = await http.put(`/dashboard/products/${id}`, payload)
  return data.data
}

export async function deleteProduct(id) {
  await http.delete(`/dashboard/products/${id}`)
}

export async function uploadProductImage(productId, file) {
  const form = new FormData()
  form.append('image', file)
  const { data } = await http.post(`/dashboard/products/${productId}/images`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}
