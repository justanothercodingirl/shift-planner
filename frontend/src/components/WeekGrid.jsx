import { weekDays } from '../utils/weeks.js'
import DayColumn from './DayColumn.jsx'

// Seven day columns, Monday first. The shifts arrive already sorted by the API.
export default function WeekGrid({ weekStart, shifts, onAdd, onSelect }) {
  return (
    <div className="week-grid">
      {weekDays(weekStart).map((date) => (
        <DayColumn
          key={date}
          date={date}
          shifts={shifts.filter((shift) => shift.date === date)}
          onAdd={onAdd}
          onSelect={onSelect}
        />
      ))}
    </div>
  )
}
