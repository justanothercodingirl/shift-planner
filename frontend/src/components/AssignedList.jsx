// The people already on a shift, each with a Remove button.
export default function AssignedList({ assignments, busy, onRemove }) {
  if (assignments.length === 0) {
    return <p className="muted">Nobody is assigned yet.</p>
  }

  return (
    <ul className="assigned-list">
      {assignments.map((assignment) => (
        <li key={assignment.id}>
          <span>{assignment.employeeName}</span>
          <button
            type="button"
            disabled={busy}
            onClick={() => onRemove(assignment)}
          >
            Remove
          </button>
        </li>
      ))}
    </ul>
  )
}
