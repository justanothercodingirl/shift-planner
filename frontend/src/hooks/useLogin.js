import { useState } from 'react'
import useAuth from './useAuth.js'

// Loading and error state for the login form.
export default function useLogin() {
  const { login } = useAuth()
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  async function submit(email, password) {
    setLoading(true)
    setError(null)
    try {
      await login(email, password)
    } catch (caught) {
      setError(caught)
    } finally {
      setLoading(false)
    }
  }

  return { submit, loading, error }
}
