import { request } from './client.js'

// The other active employees (name and positions only), for choosing a colleague.
export function listColleagues(token) {
  return request('/api/colleagues', { token })
}
