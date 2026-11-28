import SwapRequestRow from './SwapRequestRow.jsx'

// A titled table of swap requests, or a sentence when there are none.
export default function SwapRequestList({
  title,
  requests,
  emptyText,
  viewer,
  busy,
  onAction,
}) {
  return (
    <section>
      <h2>{title}</h2>
      {requests.length === 0 ? (
        <p className="muted">{emptyText}</p>
      ) : (
        <table className="data-table">
          <thead>
            <tr>
              <th>Shift</th>
              <th>Who</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {requests.map((request) => (
              <SwapRequestRow
                key={request.id}
                request={request}
                viewer={viewer}
                busy={busy}
                onAction={onAction}
              />
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}
