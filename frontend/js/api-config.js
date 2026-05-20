(() => {
  const configuredApiUrl = window.CUIDADO_API_URL || document.querySelector('meta[name="api-url"]')?.content
  const apiPort = window.CUIDADO_API_PORT || "8080"
  const defaultApiUrl = `${window.location.protocol}//${window.location.hostname}:${apiPort}/api`

  window.CuidadoConfig = {
    apiUrl: String(configuredApiUrl || defaultApiUrl).replace(/\/$/, ""),
  }
})()
