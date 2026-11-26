import { request } from './client.js'

export function assignEmployee(token, shiftId, employeeId) {
  return request(`/api/shifts/${shiftId}/assignments`, {
    method: 'POST',
    token,
    body: { employeeId },
  })
}

export function unassign(token, assignmentId) {
  return request(`/api/assignments/${assignmentId}`, {
    method: 'DELETE',
    token,
  })
}
