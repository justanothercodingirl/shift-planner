// Shown after publishing when the server had warnings (understaffed shifts):
// the roster is published, the manager is just told what is still open.
export default function PublishWarnings({ warnings, onDismiss }) {
  if (warnings.length === 0) return null

  return (
    <div className="warning-message" role="status">
      <p>Published, but some shifts are still understaffed:</p>
      <ul>
        {warnings.map((warning) => (
          <li key={warning.reason}>{warning.reason}</li>
        ))}
      </ul>
      <button type="button" onClick={onDismiss}>
        Dismiss
      </button>
    </div>
  )
}
