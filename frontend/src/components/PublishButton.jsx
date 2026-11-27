// Publishes the roster after a confirmation. Shown for drafts only.
export default function PublishButton({ onPublish, busy }) {
  function handleClick() {
    const message = 'Publish this roster? You can still change it afterwards.'
    if (window.confirm(message)) onPublish()
  }

  return (
    <button type="button" onClick={handleClick} disabled={busy}>
      {busy ? 'Publishing…' : 'Publish'}
    </button>
  )
}
