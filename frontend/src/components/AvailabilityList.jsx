import AvailabilityRow from './AvailabilityRow.jsx'

export default function AvailabilityList({ entries, onEdit, onDelete }) {
  if (entries.length === 0) {
    return (
      <p className="muted">
        Nothing entered yet: you count as available for every shift.
      </p>
    )
  }

  return (
    <table className="data-table">
      <thead>
        <tr>
          <th>When</th>
          <th>Time</th>
          <th>Type</th>
          <th />
        </tr>
      </thead>
      <tbody>
        {entries.map((entry) => (
          <AvailabilityRow
            key={entry.id}
            entry={entry}
            onEdit={onEdit}
            onDelete={onDelete}
          />
        ))}
      </tbody>
    </table>
  )
}
