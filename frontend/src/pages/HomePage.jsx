import useAuth from '../hooks/useAuth.js'

// Placeholder landing page for signed-in users. The roster week view replaces it later.
export default function HomePage() {
  const { user, logout } = useAuth()

  return (
    <main className="page">
      <h1>Shift Planner</h1>
      <p>
        Signed in as {user?.email} ({user?.role})
      </p>
      <button type="button" onClick={logout}>
        Log out
      </button>
    </main>
  )
}
