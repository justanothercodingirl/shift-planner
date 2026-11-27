import Badge from './Badge.jsx'
import PublishButton from './PublishButton.jsx'

// The page title with the roster's status and, for a draft, the Publish button.
export default function RosterHeader({ roster, onPublish, publishing }) {
  const published = roster?.status === 'published'

  return (
    <div className="page-header">
      <h1>Roster</h1>
      {roster && (
        <div className="header-actions">
          <Badge tone={published ? 'good' : 'neutral'}>
            {published ? 'Published' : 'Draft'}
          </Badge>
          {!published && (
            <PublishButton onPublish={onPublish} busy={publishing} />
          )}
        </div>
      )}
    </div>
  )
}
