import http from './http'

/**
 * Fetch the public menu for a restaurant by slug.
 * Returns the unwrapped payload: { restaurant, categories }.
 */
export async function fetchMenu(slug) {
  const { data } = await http.get(`/public/restaurants/${slug}/menu`)
  return data.data
}
