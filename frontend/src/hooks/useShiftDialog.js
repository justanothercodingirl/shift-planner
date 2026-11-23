import { useState } from 'react'

// What the shift dialog is showing: null (closed), or { shift, date } where
// shift is null when adding and the existing shift when editing.
export default function useShiftDialog() {
  const [dialog, setDialog] = useState(null)

  return {
    dialog,
    openAdd: (date) => setDialog({ shift: null, date }),
    openEdit: (shift) => setDialog({ shift, date: shift.date }),
    close: () => setDialog(null),
  }
}
