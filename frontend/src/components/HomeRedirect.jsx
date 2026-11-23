import { Navigate } from 'react-router-dom'
import useAuth from '../hooks/useAuth.js'

// "/" has no page of its own: managers land on the roster, employees on their shifts.
export default function HomeRedirect() {
  const { user } = useAuth()

  return (
    <Navigate
      to={user?.role === 'manager' ? '/roster' : '/my-shifts'}
      replace
    />
  )
}
