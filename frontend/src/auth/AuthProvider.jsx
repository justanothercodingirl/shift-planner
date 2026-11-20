import { useCallback, useMemo, useState } from 'react'
import { login as loginRequest } from '../api/auth.js'
import { AuthContext } from './AuthContext.js'
import { decodeToken } from './decodeToken.js'

// The token lives in memory only (see DECISIONS.md): a page refresh logs the user out.
export default function AuthProvider({ children }) {
  const [token, setToken] = useState(null)

  const login = useCallback(async (email, password) => {
    setToken(await loginRequest(email, password))
  }, [])

  const logout = useCallback(() => setToken(null), [])

  const value = useMemo(
    () => ({
      token,
      user: token ? decodeToken(token) : null,
      isAuthenticated: token !== null,
      login,
      logout,
    }),
    [token, login, logout],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
