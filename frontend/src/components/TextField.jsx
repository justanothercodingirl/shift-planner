export default function TextField({
  label,
  type = 'text',
  value,
  onChange,
  autoComplete,
}) {
  return (
    <label className="text-field">
      <span>{label}</span>
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        autoComplete={autoComplete}
        required
      />
    </label>
  )
}
