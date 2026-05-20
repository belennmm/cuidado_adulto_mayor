(() => {
  const keyPrefix = "cuidado.auth"
  const activeRoleKey = `${keyPrefix}.activeRole`
  const legacyTokenKey = "token"
  const legacyUserKey = "user"

  const roleBuckets = {
    admin: "admin",
    profesional: "cuidador_profesional",
    cuidador_profesional: "cuidador_profesional",
    familiar: "cuidador_familiar",
    cuidador_familiar: "cuidador_familiar",
  }

  function safeJsonParse(value) {
    try {
      return JSON.parse(value)
    } catch {
      return null
    }
  }

  function normalizeRole(role) {
    return String(role || "").trim().toLowerCase()
  }

  function getRoleBucket(role) {
    return roleBuckets[normalizeRole(role)] || ""
  }

  function getPathRoleBucket() {
    const path = window.location.pathname.toLowerCase()
    if (path.includes("/pages/admin/")) return "admin"
    if (path.includes("/pages/cuidador-profesional/")) return "cuidador_profesional"
    if (path.includes("/pages/cuidador-familiar/")) return "cuidador_familiar"
    return ""
  }

  function sessionKey(roleBucket, field) {
    return `${keyPrefix}.${roleBucket}.${field}`
  }

  function getExpectedBuckets(expectedRoles = []) {
    const buckets = expectedRoles.map(getRoleBucket).filter(Boolean)
    const pathBucket = getPathRoleBucket()
    if (!buckets.length && pathBucket) {
      buckets.push(pathBucket)
    }

    return [...new Set(buckets)]
  }

  function getLegacySession() {
    const token = localStorage.getItem(legacyTokenKey)
    const user = safeJsonParse(localStorage.getItem(legacyUserKey))
    const roleBucket = getRoleBucket(user?.role)

    if (!token || !user || !roleBucket) return null

    return { token, user, roleBucket }
  }

  function removeLegacySession() {
    localStorage.removeItem(legacyTokenKey)
    localStorage.removeItem(legacyUserKey)
  }

  function saveSession(token, user) {
    const roleBucket = getRoleBucket(user?.role)
    if (!token || !user || !roleBucket) return false

    localStorage.setItem(sessionKey(roleBucket, "token"), token)
    localStorage.setItem(sessionKey(roleBucket, "user"), JSON.stringify(user))
    localStorage.setItem(activeRoleKey, roleBucket)
    removeLegacySession()
    return true
  }

  function saveUser(user) {
    const roleBucket = getRoleBucket(user?.role) || localStorage.getItem(activeRoleKey) || getPathRoleBucket()
    if (!user || !roleBucket) return false

    localStorage.setItem(sessionKey(roleBucket, "user"), JSON.stringify(user))
    localStorage.setItem(activeRoleKey, roleBucket)
    localStorage.removeItem(legacyUserKey)
    return true
  }

  function readSession(roleBucket) {
    if (!roleBucket) return null

    const token = localStorage.getItem(sessionKey(roleBucket, "token"))
    const user = safeJsonParse(localStorage.getItem(sessionKey(roleBucket, "user")))

    if (!token || !user) return null

    return { token, user, roleBucket }
  }

  function migrateLegacySession(expectedBuckets = []) {
    const legacySession = getLegacySession()
    if (!legacySession) return null

    const isExpected = !expectedBuckets.length || expectedBuckets.includes(legacySession.roleBucket)
    if (!isExpected) return null

    saveSession(legacySession.token, legacySession.user)
    return legacySession
  }

  function getSession(expectedRoles = []) {
    const expectedBuckets = getExpectedBuckets(expectedRoles)
    const activeRole = localStorage.getItem(activeRoleKey)
    const candidates = expectedBuckets.length
      ? expectedBuckets
      : [activeRole, getPathRoleBucket()].filter(Boolean)

    for (const roleBucket of [...new Set(candidates)]) {
      const session = readSession(roleBucket)
      if (session) return session
    }

    return migrateLegacySession(expectedBuckets)
  }

  function clearSession(role) {
    const roleBucket = getRoleBucket(role) || localStorage.getItem(activeRoleKey) || getPathRoleBucket()
    if (roleBucket) {
      localStorage.removeItem(sessionKey(roleBucket, "token"))
      localStorage.removeItem(sessionKey(roleBucket, "user"))
    }

    if (!roleBucket || localStorage.getItem(activeRoleKey) === roleBucket) {
      localStorage.removeItem(activeRoleKey)
    }

    removeLegacySession()
  }

  function clearAllSessions() {
    Object.values(roleBuckets).forEach((roleBucket) => {
      localStorage.removeItem(sessionKey(roleBucket, "token"))
      localStorage.removeItem(sessionKey(roleBucket, "user"))
    })
    localStorage.removeItem(activeRoleKey)
    removeLegacySession()
  }

  function getToken(expectedRoles = []) {
    return getSession(expectedRoles)?.token || ""
  }

  function getUser(expectedRoles = []) {
    return getSession(expectedRoles)?.user || null
  }

  window.AuthSession = {
    clearAllSessions,
    clearSession,
    getRoleBucket,
    getSession,
    getToken,
    getUser,
    saveSession,
    saveUser,
  }
})()
