import { request } from './client.js'

export function listRosters(token) {
  return request('/api/rosters', { token })
}

export function getRoster(token, id) {
  return request(`/api/rosters/${id}`, { token })
}

export function createRoster(token, weekStart) {
  return request('/api/rosters', {
    method: 'POST',
    token,
    body: { weekStart },
  })
}
