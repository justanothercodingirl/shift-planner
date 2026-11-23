import EmployeeRow from './EmployeeRow.jsx'

export default function EmployeeList({ employees, onEdit, onDeactivate }) {
  if (employees.length === 0) return <p>No employees yet.</p>

  return (
    <table className="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Position</th>
          <th>Contract</th>
          <th>Weekly hours</th>
          <th>Status</th>
          <th />
        </tr>
      </thead>
      <tbody>
        {employees.map((employee) => (
          <EmployeeRow
            key={employee.id}
            employee={employee}
            onEdit={onEdit}
            onDeactivate={onDeactivate}
          />
        ))}
      </tbody>
    </table>
  )
}
