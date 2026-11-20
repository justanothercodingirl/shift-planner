import { request } from './client.js'

export async function login(email, password) {
  const data = await request('/api/login', {
    method: 'POST',
    body: { email, password },
  })
  return data.token
}
