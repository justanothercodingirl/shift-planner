import { CONTRACT_TYPES, labelOf } from '../constants/options.js'
import { describePositions } from '../utils/positions.js'
import Badge from './Badge.jsx'

export default function EmployeeRow({ employee, onEdit, onDeactivate }) {
  return (
    <tr className={employee.active ? undefined : 'inactive'}>
      <td>{employee.name}</td>
      <td>{employee.email}</td>
      <td>{describePositions(employee)}</td>
      <td>{labelOf(CONTRACT_TYPES, employee.contractType)}</td>
      <td>{employee.maxWeeklyHours} h</td>
      <td>
        <Badge tone={employee.active ? 'good' : 'muted'}>
          {employee.active ? 'Active' : 'Inactive'}
        </Badge>
      </td>
      <td className="actions">
        <button type="button" onClick={() => onEdit(employee)}>
          Edit
        </button>
        {employee.active && (
          <button type="button" onClick={() => onDeactivate(employee)}>
            Deactivate
          </button>
        )}
      </td>
    </tr>
  )
}
