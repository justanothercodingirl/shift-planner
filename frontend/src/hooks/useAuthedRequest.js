import { useCallback } from 'react'
import { ApiError } from '../api/client.js'
import useAuth from './useAuth.js'

// Runs an API call with the current token: run((token) => listEmployees(token)).
// A 401 means the token is missing or expired, so the user is logged out here,
// in one place, and ProtectedRoute sends them back to the login page.
export default function useAuthedRequest() {
  const { token, logout } = useAuth()

  return useCallback(
    async (call) => {
      try {
        return await call(token)
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) logout()
        throw error
      }
    },
    [token, logout],
  )
}
