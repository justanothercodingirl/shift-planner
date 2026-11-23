import { formatWeek } from '../utils/weeks.js'

export default function WeekNavigator({
  weekStart,
  onPrevious,
  onNext,
  onToday,
}) {
  return (
    <div className="week-navigator">
      <button type="button" onClick={onPrevious}>
        ← Previous
      </button>
      <strong>{formatWeek(weekStart)}</strong>
      <button type="button" onClick={onNext}>
        Next →
      </button>
      <button type="button" onClick={onToday}>
        This week
      </button>
    </div>
  )
}
