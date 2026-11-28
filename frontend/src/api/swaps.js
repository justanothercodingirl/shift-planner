import { request } from './client.js'

export function listSwapRequests(token) {
  return request('/api/swap-requests', { token })
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
