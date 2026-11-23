import { NavLink, Outlet } from 'react-router-dom'
import useAuth from '../hooks/useAuth.js'

// The frame around every signed-in page: navigation, who is signed in, log out.
export default function AppLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="layout">
      <header className="layout-header">
        <strong>Shift Planner</strong>
        <nav>
          <NavLink to="/" end>
            Home
          </NavLink>
          {user?.role === 'manager' && (
            <NavLink to="/employees">Employees</NavLink>
          )}
        </nav>
        <div className="layout-user">
          <span>
            {user?.email} ({user?.role})
          </span>
          <button type="button" onClick={logout}>
            Log out
          </button>
        </div>
      </header>
      <Outlet />
    </div>
  )
}
