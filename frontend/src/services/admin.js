import http from './http'

// Platform-administration endpoints. All are gated server-side to super-admins.

export async function getPlatformOverview() {
  const { data } = await http.get('/admin/overview')
  return data.data
}

export async function getRestaurants(params = {}) {
  const { data } = await http.get('/admin/restaurants', { params })
  return data.data
}

export async function updateRestaurantStatus(id, status) {
  const { data } = await http.patch(`/admin/restaurants/${id}/status`, { status })
  return data.data
}

export async function deleteRestaurant(id) {
  await http.delete(`/admin/restaurants/${id}`)
}

export async function getUsers(params = {}) {
  const { data } = await http.get('/admin/users', { params })
  return data.data
}

export async function updateUserStatus(id, isActive) {
  const { data } = await http.patch(`/admin/users/${id}/status`, { is_active: isActive })
  return data.data
}

// Restaurant platform statuses, with display labels and badge tones.
export const RESTAURANT_STATUSES = [
  { value: 'pending', label: 'Pending', tone: 'bg-amber-100 text-amber-700' },
  { value: 'active', label: 'Active', tone: 'bg-emerald-100 text-emerald-700' },
  { value: 'suspended', label: 'Suspended', tone: 'bg-red-100 text-red-600' },
  { value: 'closed', label: 'Closed', tone: 'bg-stone-200 text-stone-600' },
]

export function restaurantStatusMeta(value) {
  return (
    RESTAURANT_STATUSES.find((s) => s.value === value) || {
      value,
      label: value,
      tone: 'bg-stone-100 text-stone-600',
    }
  )
}

// Role display labels for the users table.
export const ROLE_LABELS = {
  'super-admin': 'Super Admin',
  'restaurant-owner': 'Owner',
  'restaurant-manager': 'Manager',
  waiter: 'Waiter',
  'kitchen-staff': 'Kitchen Staff',
}

export function roleLabel(role) {
  return ROLE_LABELS[role] || role
}
