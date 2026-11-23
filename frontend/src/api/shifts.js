import { request } from './client.js'

export function createShift(token, rosterId, shift) {
  return request(`/api/rosters/${rosterId}/shifts`, {
    method: 'POST',
    token,
    body: shift,
  })
}

export function updateShift(token, id, shift) {
  return request(`/api/shifts/${id}`, { method: 'PUT', token, body: shift })
}

export function deleteShift(token, id) {
  return request(`/api/shifts/${id}`, { method: 'DELETE', token })
}
