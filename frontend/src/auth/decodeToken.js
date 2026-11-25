// Reads the email, role and employee id out of a JWT so the UI can show who is
// signed in and build the URLs of their own data.
// This is for display only: the server checks the token's signature and
// enforces permissions, never the browser.
export function decodeToken(token) {
  try {
    const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')
    const bytes = Uint8Array.from(atob(payload), (char) => char.charCodeAt(0))
    const {
      username,
      roles = [],
      employeeId = null,
    } = JSON.parse(new TextDecoder().decode(bytes))
    return {
      email: username,
      role: roles.includes('ROLE_MANAGER') ? 'manager' : 'employee',
      employeeId,
    }
  } catch {
    return null
  }
}
