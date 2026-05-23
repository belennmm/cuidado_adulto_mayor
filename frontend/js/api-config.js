(() => {
  const configuredApiUrl = window.CUIDADO_API_URL || document.querySelector('meta[name="api-url"]')?.content
  const apiPort = window.CUIDADO_API_PORT || "8080"
  const defaultApiUrl = `${window.location.protocol}//${window.location.hostname}:${apiPort}/api`
  const apiUrl = String(configuredApiUrl || defaultApiUrl).replace(/\/$/, "")

  window.CuidadoConfig = {
    apiUrl,
  }

  function isFormData(value) {
    return typeof FormData !== "undefined" && value instanceof FormData
  }

  function isAbsoluteUrl(value) {
    return /^https?:\/\//i.test(String(value || ""))
  }

  function buildUrl(path = "") {
    if (isAbsoluteUrl(path)) return String(path)
    const normalizedPath = String(path || "").startsWith("/") ? path : `/${path}`
    return `${apiUrl}${normalizedPath}`
  }

  function getToken(expectedRoles = []) {
    if (window.AuthSession?.getToken) {
      return window.AuthSession.getToken(expectedRoles) || localStorage.getItem("token") || ""
    }

    return localStorage.getItem("token") || ""
  }

  function getHeaders(options = {}) {
    const token = options.token ?? getToken(options.expectedRoles || [])
    const body = options.body
    const headers = {
      Accept: "application/json",
      ...(body && !isFormData(body) ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    }

    if (options.auth === false) {
      delete headers.Authorization
    }

    return headers
  }

  function firstValidationMessage(errors) {
    const firstError = Object.values(errors || {})[0]
    if (Array.isArray(firstError) && firstError.length) return firstError[0]
    if (typeof firstError === "string") return firstError
    return ""
  }

  function getErrorMessage(data, fallback = "No se pudo completar la solicitud.") {
    return data?.message || firstValidationMessage(data?.errors) || fallback
  }

  function createApiError(response, data, fallback) {
    const error = new Error(getErrorMessage(data, fallback))
    error.status = response.status
    error.statusText = response.statusText
    error.data = data
    error.errors = data?.errors || {}
    return error
  }

  async function parseResponse(response) {
    const contentType = response.headers.get("content-type") || ""
    if (contentType.includes("application/json")) {
      return response.json().catch(() => ({}))
    }

    const text = await response.text().catch(() => "")
    return text ? { message: text } : {}
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

    const response = await fetch(buildUrl(path), {
      cache: "no-store",
      ...fetchOptions,
      headers: getHeaders({
        auth,
        body: fetchOptions.body,
        expectedRoles,
        headers,
        token,
      }),
    })
    const data = await parseResponse(response)

    if (!response.ok) {
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
    getToken,
  }
})()
