import http from './http'
import i18n from '@/i18n'

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

export async function updateUserRoles(id, roles) {
  const { data } = await http.patch(`/admin/users/${id}/roles`, { roles })
  return data.data
}

export async function getRoles() {
  const { data } = await http.get('/admin/roles')
  return data.data
}

export async function updateRolePermissions(roleId, permissions) {
  const { data } = await http.patch(`/admin/roles/${roleId}/permissions`, { permissions })
  return data.data
}

// The category catalog restaurants pick their menu sections from.
export async function getCategoryPresets() {
  const { data } = await http.get('/admin/category-presets')
  return data.data
}

export async function saveCategoryPreset(payload, id) {
  const { data } = id
    ? await http.put(`/admin/category-presets/${id}`, payload)
    : await http.post('/admin/category-presets', payload)
  return data.data
}

export async function deleteCategoryPreset(id) {
  await http.delete(`/admin/category-presets/${id}`)
}

// Restaurant platform statuses, with display labels and badge tones.
const STATUS_DEFS = [
  { value: 'pending', tone: 'bg-amber-100 text-amber-700' },
  { value: 'active', tone: 'bg-emerald-100 text-emerald-700' },
  { value: 'suspended', tone: 'bg-red-100 text-red-600' },
  { value: 'closed', tone: 'bg-stone-200 text-stone-600' },
]

// Labels resolve on read, so they follow the active locale.
export const RESTAURANT_STATUSES = STATUS_DEFS.map((s) => ({
  value: s.value,
  tone: s.tone,
  get label() {
    return i18n.global.t(`admin.statuses.${s.value}`)
  },
}))

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
export const ROLE_SLUGS = [
  'super-admin',
  'restaurant-owner',
  'restaurant-manager',
  'waiter',
  'kitchen-staff',
]

export function roleLabel(role) {
  const key = `admin.roles.${role}`
  return i18n.global.te(key) ? i18n.global.t(key) : role
}
