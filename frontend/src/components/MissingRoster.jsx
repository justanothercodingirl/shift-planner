import { formatWeek } from '../utils/weeks.js'

export default function MissingRoster({ weekStart, onCreate, creating }) {
  return (
    <div className="panel empty-state">
      <p>There is no roster for {formatWeek(weekStart)} yet.</p>
      <button type="button" onClick={onCreate} disabled={creating}>
        {creating ? 'Creating…' : 'Create roster for this week'}
      </button>
    </div>
  )
}
