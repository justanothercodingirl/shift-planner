import { useState } from 'react'
import EmployeeForm from '../components/EmployeeForm.jsx'
import EmployeeList from '../components/EmployeeList.jsx'
import ErrorMessage from '../components/ErrorMessage.jsx'
import useEmployees from '../hooks/useEmployees.js'

const NEW = 'new'

export default function EmployeesPage() {
  const { employees, loading, error, create, update, deactivate } =
    useEmployees()
  // null = form closed, NEW = adding, otherwise the employee being edited
  const [editing, setEditing] = useState(null)

  async function handleSubmit(payload) {
    const saved =
      editing === NEW
        ? await create(payload)
        : await update(editing.id, payload)
    if (saved) setEditing(null)
    return saved
  }

  function handleDeactivate(employee) {
    const message = `Deactivate ${employee.name}? They stay in past rosters but cannot be assigned to new shifts.`
    if (window.confirm(message)) deactivate(employee.id)
  }

  return (
    <main className="page page-wide">
      <div className="page-header">
        <h1>Employees</h1>
        <button type="button" onClick={() => setEditing(NEW)}>
          Add employee
        </button>
      </div>
      <ErrorMessage error={error} />
      {editing && (
        <EmployeeForm
          key={editing === NEW ? NEW : editing.id}
          employee={editing === NEW ? null : editing}
          onSubmit={handleSubmit}
          onCancel={() => setEditing(null)}
        />
      )}
      {loading ? (
        <p>Loading…</p>
      ) : (
        <EmployeeList
          employees={employees}
          onEdit={setEditing}
          onDeactivate={handleDeactivate}
        />
      )}
    </main>
  )
}
