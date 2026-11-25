import { useState } from 'react'
import AvailabilityForm from '../components/AvailabilityForm.jsx'
import AvailabilityList from '../components/AvailabilityList.jsx'
import ErrorMessage from '../components/ErrorMessage.jsx'
import useAuth from '../hooks/useAuth.js'
import useAvailability from '../hooks/useAvailability.js'

const NEW = 'new'

// Employees enter the times they cannot work (or would prefer to). They count
// as available by default, so only the exceptions are listed.
export default function AvailabilityPage() {
  const { user } = useAuth()
  const employeeId = user?.employeeId ?? null
  const { entries, loading, error, create, update, remove } =
    useAvailability(employeeId)
  // null = form closed, NEW = adding, otherwise the entry being edited
  const [editing, setEditing] = useState(null)

  async function handleSubmit(payload) {
    const saved =
      editing === NEW
        ? await create(payload)
        : await update(editing.id, payload)
    if (saved) setEditing(null)
    return saved
  }

  function handleDelete(entry) {
    if (window.confirm('Delete this entry?')) remove(entry.id)
  }

  if (employeeId === null) {
    return (
      <main className="page">
        <h1>My availability</h1>
        <p>This login has no employee profile, so there is nothing to edit.</p>
      </main>
    )
  }

  return (
    <main className="page page-wide">
      <div className="page-header">
        <h1>My availability</h1>
        <button type="button" onClick={() => setEditing(NEW)}>
          Add availability
        </button>
      </div>
      <ErrorMessage error={error} />
      {editing && (
        <AvailabilityForm
          key={editing === NEW ? NEW : editing.id}
          entry={editing === NEW ? null : editing}
          onSubmit={handleSubmit}
          onCancel={() => setEditing(null)}
        />
      )}
      {loading ? (
        <p>Loading…</p>
      ) : (
        <AvailabilityList
          entries={entries}
          onEdit={setEditing}
          onDelete={handleDelete}
        />
      )}
    </main>
  )
}
