// Reads the email and role out of a JWT so the UI can show who is signed in.
// This is for display only: the server checks the token's signature and
// enforces permissions, never the browser.
export function decodeToken(token) {
  try {
    const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')
    const bytes = Uint8Array.from(atob(payload), (char) => char.charCodeAt(0))
    const { username, roles = [] } = JSON.parse(new TextDecoder().decode(bytes))
    return {
      email: username,
      role: roles.includes('ROLE_MANAGER') ? 'manager' : 'employee',
    }
  } catch {
    return null
  }
}
