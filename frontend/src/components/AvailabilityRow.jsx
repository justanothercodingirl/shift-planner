import { AVAILABILITY_TYPES, labelOf } from '../constants/options.js'
import { describeDay } from '../utils/availability.js'
import Badge from './Badge.jsx'

export default function AvailabilityRow({ entry, onEdit, onDelete }) {
  return (
    <tr>
      <td>{describeDay(entry)}</td>
      <td>
        {entry.startTime} to {entry.endTime}
      </td>
      <td>
        <Badge tone={entry.type === 'preferred' ? 'good' : 'neutral'}>
          {labelOf(AVAILABILITY_TYPES, entry.type)}
        </Badge>
      </td>
      <td className="actions">
        <button type="button" onClick={() => onEdit(entry)}>
          Edit
        </button>
        <button type="button" onClick={() => onDelete(entry)}>
          Delete
        </button>
      </td>
    </tr>
  )
}
