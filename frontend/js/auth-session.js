(() => {
  const userKey = "cuidado.session.user"
  const buckets = { admin: "admin", administrador: "admin", profesional: "cuidador_profesional", cuidador_profesional: "cuidador_profesional", familiar: "cuidador_familiar", cuidador_familiar: "cuidador_familiar" }
  let resolved = false
  let pending = null
  function purgeLegacyCredentials() {
    for (const storage of [localStorage, sessionStorage]) {
      for (const key of Object.keys(storage)) {
        if (key === "token" || key === "user" || key.startsWith("cuidado.auth.")) storage.removeItem(key)
      }
    }
  }
  function getRoleBucket(role) { return buckets[String(role || "").trim().toLowerCase()] || "" }
  function getUser(expectedRoles = []) {
    let user
    try { user = JSON.parse(sessionStorage.getItem(userKey)) } catch { return null }
    if (!user || !getRoleBucket(user.role)) return null
    return !expectedRoles.length || expectedRoles.some(role => getRoleBucket(role) === getRoleBucket(user.role)) ? user : null
  }
  function saveUser(user) {
    if (!user || !getRoleBucket(user.role)) return false
    purgeLegacyCredentials()
    sessionStorage.setItem(userKey, JSON.stringify(user))
    resolved = true
    return true
  }
  function clearSession() {
    purgeLegacyCredentials()
    sessionStorage.removeItem(userKey)
    resolved = true
  }
  function hasSession(expectedRoles = []) {
    // Pending identity is not an authorization decision: every operation still goes to the server.
    return !resolved || Boolean(getUser(expectedRoles))
  }
  function getSession(expectedRoles = []) {
    const user = getUser(expectedRoles)
    return user ? { user, roleBucket: getRoleBucket(user.role) } : null
  }
  async function refresh() {
    if (!pending) pending = window.CuidadoApi.fetchJson("/me").then(data => {
      if (!saveUser(data.user)) throw new Error("Sesion no valida.")
      return data.user
    }).catch(error => { clearSession(); throw error }).finally(() => { pending = null })
    return pending
  }
  purgeLegacyCredentials()
  window.AuthSession = { clearAllSessions: clearSession, clearSession, getRoleBucket, getSession, hasSession, getUser, saveSession: saveUser, saveUser, refresh }
})()
