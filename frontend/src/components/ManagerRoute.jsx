import { Navigate, Outlet } from 'react-router-dom'
import useAuth from '../hooks/useAuth.js'

// Pages inside this route are for managers only. The server enforces it too.
export default function ManagerRoute() {
  const { user } = useAuth()

  return user?.role === 'manager' ? <Outlet /> : <Navigate to="/" replace />
}
