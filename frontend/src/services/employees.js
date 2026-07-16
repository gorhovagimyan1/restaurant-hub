import http from './http'

/**
 * Fetch the current restaurant's staff.
 */
export async function fetchEmployees() {
  const { data } = await http.get('/dashboard/employees')
  return data.data
}

/**
 * Invite a new employee. The backend emails them a link to set a password.
 */
export async function inviteEmployee(payload) {
  const { data } = await http.post('/dashboard/employees', payload)
  return data.data
}

/**
 * Update an employee's role and/or active status.
 */
export async function updateEmployee(id, payload) {
  const { data } = await http.put(`/dashboard/employees/${id}`, payload)
  return data.data
}

/**
 * Remove an employee from the restaurant.
 */
export async function deleteEmployee(id) {
  await http.delete(`/dashboard/employees/${id}`)
}

/**
 * Roles an owner/manager may assign to staff, with display labels.
 */
export const ASSIGNABLE_ROLES = [
  { value: 'restaurant-manager', label: 'Manager' },
  { value: 'waiter', label: 'Waiter' },
  { value: 'kitchen-staff', label: 'Kitchen Staff' },
]

export function roleLabel(role) {
  return ASSIGNABLE_ROLES.find((r) => r.value === role)?.label || role
}
