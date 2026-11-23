// A small label such as "Active" or "Draft". tone: neutral | good | muted
export default function Badge({ tone = 'neutral', children }) {
  return <span className={`badge badge-${tone}`}>{children}</span>
}
