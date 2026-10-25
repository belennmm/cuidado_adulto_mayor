(() => {
  const configuredApiUrl = window.CUIDADO_API_URL || document.querySelector('meta[name="api-url"]')?.content
  const apiPort = window.CUIDADO_API_PORT
  const defaultApiUrl = apiPort
    ? `${window.location.protocol}//${window.location.hostname}:${apiPort}/api`
    : "/api"
  const apiUrl = String(configuredApiUrl || defaultApiUrl).replace(/\/$/, "")
  const apiBase = window.CuidadoUrls.httpUrl(apiUrl)
  if (!apiBase || apiBase.search || apiBase.hash) throw new Error("URL de API no válida.")

  window.CuidadoConfig = {
    apiUrl,
  }

  function isFormData(value) {
    return typeof FormData !== "undefined" && value instanceof FormData
  }

  function buildUrl(path = "") {
    const value = String(path || "")
    const absolute = /^[a-z][a-z0-9+.-]*:/i.test(value) || value.startsWith("//")
    const candidate = absolute ? value : `${apiUrl}/${value.replace(/^\//, "")}`
    const url = window.CuidadoUrls.httpUrl(candidate)
    const prefix = apiBase.pathname.replace(/\/$/, "")
    if (!url || url.origin !== apiBase.origin || url.hash
      || !(url.pathname === prefix || url.pathname.startsWith(`${prefix}/`))) {
      throw new Error("URL de API no permitida.")
    }
    for (const key of url.searchParams.keys()) if (/password|token|secret|authorization|cookie|challenge|(?:^|_)code(?:s|$)/i.test(key)) throw new Error("No envies credenciales en la URL.")
    return candidate
  }

  function hasSession(expectedRoles = []) {
    return window.AuthSession?.hasSession(expectedRoles) ?? true
  }
  function csrfToken() {
    const value = document.cookie.split("; ").find(cookie => cookie.startsWith("XSRF-TOKEN="))?.slice(11)
    try { return value ? decodeURIComponent(value) : "" } catch { return "" }
  }
  function getHeaders(options = {}) {
    const headers = new Headers(options.headers || {})
    headers.delete("Authorization")
    headers.delete("X-CSRF-TOKEN")
    headers.delete("X-XSRF-TOKEN")
    headers.set("Accept", "application/json")
    headers.set("X-Cuidado-Session", "1")
    if (options.body && !isFormData(options.body)) headers.set("Content-Type", "application/json")
    if (csrfToken()) headers.set("X-XSRF-TOKEN", csrfToken())
    return Object.fromEntries(headers.entries())
  }
  let csrfRequest = null
  async function ensureCsrf(force = false) {
    if (!force && csrfToken()) return
    if (!csrfRequest) csrfRequest = fetchJson("/csrf-cookie", { method: "GET" }).finally(() => { csrfRequest = null })
    await csrfRequest
    if (!csrfToken()) throw new Error("No se pudo iniciar la proteccion CSRF. Verifica las cookies del navegador.")
  }

  function firstValidationMessage(errors) {
    const firstError = Object.values(errors || {})[0]
    if (Array.isArray(firstError) && firstError.length) return firstError[0]
    if (typeof firstError === "string") return firstError
    return ""
  }

  function getErrorMessage(data, fallback = "No se pudo completar la solicitud.") {
    if (data?.code === "validation_failed") {
      return firstValidationMessage(data?.errors) || data?.message || fallback
    }
    return data?.message || firstValidationMessage(data?.errors) || fallback
  }

  function createApiError(response, data, fallback) {
    if (response.status >= 500) data = { message: "Error interno del servidor." }
    const error = new Error(getErrorMessage(data, fallback))
    error.status = response.status
    error.statusText = response.status >= 500 ? "Internal Server Error" : response.statusText
    error.code = typeof data?.code === "string" ? data.code : "http_error"
    error.data = data
    error.errors = data?.errors || {}
    return error
  }

  const sessionExpiredMessage = "Tu sesión ha expirado. Inicia sesión nuevamente para continuar."

  function showSessionExpiredNotice() {
    if (document.getElementById("sessionExpiredNotice")) return
    const notice = document.createElement("section")
    notice.id = "sessionExpiredNotice"
    notice.className = "session-expired-notice"
    notice.setAttribute("role", "alert")
    const message = document.createElement("p")
    message.textContent = sessionExpiredMessage
    const login = document.createElement("a")
    login.href = "../../index.html"
    login.textContent = "Iniciar sesión"
    notice.append(message, login)
    document.body.append(notice)
  }

  async function parseResponse(response) {
    const contentType = response.headers.get("content-type") || ""
    if (contentType.includes("application/json")) {
      return response.json().catch(() => ({}))
    }

    const text = await response.text().catch(() => "")
    return {}
  }

  async function fetchJson(path, options = {}) {
    const {
      expectedRoles,
      fallbackError,
      token,
      auth,
      headers,
      ...fetchOptions
    } = options

    const url = buildUrl(path)
    const method = String(fetchOptions.method || "GET").toUpperCase()
    const endpoint = new URL(url, window.location.href).pathname.slice(apiBase.pathname.replace(/\/$/, "").length)
    const publicAuthentication = ["/login", "/register", "/forgot-password", "/reset-password", "/mfa/verify"].includes(endpoint)
    if (!["GET", "HEAD", "OPTIONS"].includes(method)) {
      await ensureCsrf(publicAuthentication)
    }
    const response = await fetch(url, {
      cache: "no-store",
      ...fetchOptions,
      redirect: "error",
      credentials: "include",
      headers: getHeaders({
        auth,
        body: fetchOptions.body,
        expectedRoles,
        headers,
        token,
      }),
    })
    let data = await parseResponse(response)

    if (!response.ok) {
      if (response.status === 401) {
        const session = window.AuthSession
        // clearSession is the stable public contract used by existing pages
        // and integrations. expire adds transition information, but must not
        // replace the cleanup call when both methods are available.
        const newlyExpired = session?.expire?.()
        session?.clearSession?.()
        if (expectedRoles?.length && newlyExpired !== false) {
          window.dispatchEvent(new CustomEvent("cuidado:session-expired"))
        }
        if (auth !== false && !publicAuthentication && !["/csrf-cookie", "/ping"].includes(endpoint)) {
          data = { message: sessionExpiredMessage, code: "session_expired" }
          if (!["GET", "HEAD", "OPTIONS"].includes(method)) showSessionExpiredNotice()
        }
      }
      throw createApiError(response, data, fallbackError)
    }

    return data
  }

  window.CuidadoApi = {
    apiUrl,
    baseUrl: apiUrl,
    buildUrl,
    createApiError,
    fetchJson,
    getErrorMessage,
    getHeaders,
    hasSession,
  }
})()
