import { useEffect, useRef } from 'react'
import ShiftForm from './ShiftForm.jsx'

// A modal window around the shift form, using the browser's built-in <dialog>
// element: it blocks the page behind it and Esc closes it.
// dialog is null (closed) or { shift, date }, see useShiftDialog.
export default function ShiftDialog({
  dialog,
  weekStart,
  error,
  onSave,
  onDelete,
  onClose,
}) {
  const ref = useRef(null)

  useEffect(() => {
    const element = ref.current
    if (dialog && !element.open) element.showModal()
    if (!dialog && element.open) element.close()
  }, [dialog])

  return (
    <dialog ref={ref} className="shift-dialog" onClose={onClose}>
      {dialog && (
        <ShiftForm
          shift={dialog.shift}
          date={dialog.date}
          weekStart={weekStart}
          error={error}
          onSubmit={onSave}
          onDelete={onDelete}
          onCancel={onClose}
        />
      )}
    </dialog>
  )
}
