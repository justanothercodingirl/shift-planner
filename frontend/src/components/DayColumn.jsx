import { formatDay } from '../utils/weeks.js'
import ShiftCard from './ShiftCard.jsx'

export default function DayColumn({ date, shifts, onAdd, onSelect, onAssign }) {
  return (
    <section className="day-column">
      <h3>{formatDay(date)}</h3>
      {shifts.length === 0 && <p className="muted">No shifts</p>}
      {shifts.map((shift) => (
        <ShiftCard
          key={shift.id}
          shift={shift}
          onSelect={onSelect}
          onAssign={onAssign}
        />
      ))}
      <button type="button" className="add-shift" onClick={() => onAdd(date)}>
        + Add shift
      </button>
    </section>
  )
}
