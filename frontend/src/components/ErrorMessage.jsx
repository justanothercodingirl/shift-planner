// The one component that shows API errors (message plus rule/validation violations).
export default function ErrorMessage({ error }) {
  if (!error) return null

  return (
    <div className="error-message" role="alert">
      <p>{error.message}</p>
      {error.violations?.length > 0 && (
        <ul>
          {error.violations.map((violation) => (
            <li key={`${violation.code}-${violation.reason}`}>
              {violation.reason}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
