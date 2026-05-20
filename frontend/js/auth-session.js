(() => {
  const keyPrefix = "cuidado.auth"
  const activeRoleKey = `${keyPrefix}.activeRole`
  const tabRoleKey = `${keyPrefix}.tab.role`
  const tabTokenKey = `${keyPrefix}.tab.token`
  const tabUserKey = `${keyPrefix}.tab.user`
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

  function getTabSession() {
    const token = sessionStorage.getItem(tabTokenKey)
    const user = safeJsonParse(sessionStorage.getItem(tabUserKey))
    const roleBucket = sessionStorage.getItem(tabRoleKey) || getRoleBucket(user?.role)

    if (!token || !user || !roleBucket) return null

    return { token, user, roleBucket }
  }

  function saveTabSession(token, user, roleBucket) {
    sessionStorage.setItem(tabTokenKey, token)
    sessionStorage.setItem(tabUserKey, JSON.stringify(user))
    sessionStorage.setItem(tabRoleKey, roleBucket)
  }

  function clearTabSession() {
    sessionStorage.removeItem(tabTokenKey)
    sessionStorage.removeItem(tabUserKey)
    sessionStorage.removeItem(tabRoleKey)
  }

  function removeLegacySession() {
    localStorage.removeItem(legacyTokenKey)
    localStorage.removeItem(legacyUserKey)
  }

  function saveSession(token, user) {
    const roleBucket = getRoleBucket(user?.role)
    if (!token || !user || !roleBucket) return false

    saveTabSession(token, user, roleBucket)
    localStorage.setItem(sessionKey(roleBucket, "token"), token)
    localStorage.setItem(sessionKey(roleBucket, "user"), JSON.stringify(user))
    localStorage.setItem(activeRoleKey, roleBucket)
    removeLegacySession()
    return true
  }

  function saveUser(user) {
    const tabSession = getTabSession()
    const roleBucket = getRoleBucket(user?.role) || tabSession?.roleBucket || localStorage.getItem(activeRoleKey) || getPathRoleBucket()
    if (!user || !roleBucket) return false

    if (tabSession?.token) {
      saveTabSession(tabSession.token, user, roleBucket)
    }

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

  function hydrateTabSession(session) {
    if (session?.token && session.user && session.roleBucket) {
      saveTabSession(session.token, session.user, session.roleBucket)
    }

    return session
  }

  function getSession(expectedRoles = []) {
    const expectedBuckets = getExpectedBuckets(expectedRoles)
    const tabSession = getTabSession()
    if (tabSession) {
      const isExpected = !expectedBuckets.length || expectedBuckets.includes(tabSession.roleBucket)
      if (isExpected) return tabSession
    }

    const activeRole = sessionStorage.getItem(tabRoleKey) || localStorage.getItem(activeRoleKey)
    const candidates = expectedBuckets.length
      ? expectedBuckets
      : [activeRole, getPathRoleBucket()].filter(Boolean)

    for (const roleBucket of [...new Set(candidates)]) {
      const session = readSession(roleBucket)
      if (session) return hydrateTabSession(session)
    }

    return migrateLegacySession(expectedBuckets)
  }

  function clearSession(role) {
    const tabSession = getTabSession()
    const roleBucket = getRoleBucket(role) || tabSession?.roleBucket || localStorage.getItem(activeRoleKey) || getPathRoleBucket()
    clearTabSession()

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
    clearTabSession()
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
