import ErrorMessage from '../components/ErrorMessage.jsx'
import MyShiftList from '../components/MyShiftList.jsx'
import useMyShifts from '../hooks/useMyShifts.js'

// The employee's own upcoming shifts from published rosters.
export default function MyShiftsPage() {
  const { shifts, loading, error } = useMyShifts()

  return (
    <main className="page page-wide">
      <h1>My shifts</h1>
      <ErrorMessage error={error} />
      {loading ? <p>Loading…</p> : <MyShiftList shifts={shifts} />}
    </main>
  )
}
