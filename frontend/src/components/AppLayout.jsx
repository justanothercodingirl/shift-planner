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
          {user?.role === 'manager' ? (
            <>
              <NavLink to="/roster">Roster</NavLink>
              <NavLink to="/employees">Employees</NavLink>
            </>
          ) : (
            <NavLink to="/my-shifts">My shifts</NavLink>
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
