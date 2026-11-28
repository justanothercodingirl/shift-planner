import { request } from './client.js'

export function listSwapRequests(token) {
  return request('/api/swap-requests', { token })
}

// The colleagues who could take over one of my shifts today: the right position
// and no rule broken (overlap, rest, weekly hours, availability).
export function listSwapCandidates(token, assignmentId) {
  return request(`/api/assignments/${assignmentId}/swap-candidates`, { token })
}

// Asks a colleague to take over one of the signed-in employee's own shifts.
export function requestSwap(token, assignmentId, targetEmployeeId) {
  return request(`/api/assignments/${assignmentId}/swap-requests`, {
    method: 'POST',
    token,
    body: { targetEmployeeId },
  })
}

// action: 'accept' | 'decline' | 'cancel' | 'approve' | 'reject'
export function swapAction(token, id, action) {
  return request(`/api/swap-requests/${id}/${action}`, {
    method: 'POST',
    token,
  })
}
