import { request } from './client.js'

export function listAvailabilities(token, employeeId) {
  return request(`/api/employees/${employeeId}/availabilities`, { token })
}

export function createAvailability(token, employeeId, availability) {
  return request(`/api/employees/${employeeId}/availabilities`, {
    method: 'POST',
    token,
    body: availability,
  })
}

export function updateAvailability(token, id, availability) {
  return request(`/api/availabilities/${id}`, {
    method: 'PUT',
    token,
    body: availability,
  })
}

export function deleteAvailability(token, id) {
  return request(`/api/availabilities/${id}`, { method: 'DELETE', token })
}
