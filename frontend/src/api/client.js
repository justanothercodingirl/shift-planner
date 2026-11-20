// The one place that talks to the server. Components never call fetch directly.

export class ApiError extends Error {
  constructor({ status, code, message, violations = [] }) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.violations = violations
  }
}

export async function request(path, { method = 'GET', body, token } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = `Bearer ${token}`

  let response
  try {
    response = await fetch(path, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError({
      status: 0,
      code: 'NETWORK_ERROR',
      message: 'Could not reach the server. Please try again.',
    })
  }

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    // Every API error has the shape { error: { code, message, violations? } }.
    const error = data?.error
    throw new ApiError({
      status: response.status,
      code: error?.code ?? 'UNKNOWN_ERROR',
      message: error?.message ?? 'Something went wrong.',
      violations: error?.violations ?? [],
    })
  }

  return data
}
