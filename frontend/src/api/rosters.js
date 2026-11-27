import { request } from './client.js'

export function listRosters(token) {
  return request('/api/rosters', { token })
}

export function getRoster(token, id) {
  return request(`/api/rosters/${id}`, { token })
}

// Publishes a draft roster. The answer is the roster plus `warnings` (for
// example understaffed shifts): publishing still happened.
export function publishRoster(token, id) {
  return request(`/api/rosters/${id}/publish`, { method: 'POST', token })
}

export function createRoster(token, weekStart) {
  return request('/api/rosters', {
    method: 'POST',
    token,
    body: { weekStart },
  })
}
