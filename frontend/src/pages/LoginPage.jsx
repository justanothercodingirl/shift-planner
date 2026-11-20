import { Navigate } from 'react-router-dom'
import ErrorMessage from '../components/ErrorMessage.jsx'
import LoginForm from '../components/LoginForm.jsx'
import useAuth from '../hooks/useAuth.js'
import useLogin from '../hooks/useLogin.js'

export default function LoginPage() {
  const { isAuthenticated } = useAuth()
  const { submit, loading, error } = useLogin()

  if (isAuthenticated) return <Navigate to="/" replace />

  return (
    <main className="page">
      <h1>Shift Planner</h1>
      <p>Sign in to continue.</p>
      <ErrorMessage error={error} />
      <LoginForm onSubmit={submit} loading={loading} />
    </main>
  )
}
