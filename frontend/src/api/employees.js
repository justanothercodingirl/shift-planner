import { request } from './client.js'

export function listEmployees(token) {
  return request('/api/employees', { token })
}

export function createEmployee(token, employee) {
  return request('/api/employees', { method: 'POST', token, body: employee })
}

export function updateEmployee(token, id, employee) {
  return request(`/api/employees/${id}`, {
    method: 'PUT',
    token,
    body: employee,
  })
}

export function deactivateEmployee(token, id) {
  return request(`/api/employees/${id}/deactivate`, { method: 'POST', token })
}
