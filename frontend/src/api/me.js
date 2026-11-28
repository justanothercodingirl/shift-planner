import { request } from './client.js'

// The signed-in employee's own shifts that have not ended yet (published rosters only).
export function listMyAssignments(token) {
  return request('/api/me/assignments', { token })
}
