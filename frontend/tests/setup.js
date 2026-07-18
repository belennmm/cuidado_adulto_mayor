import { afterEach, beforeEach, vi } from "vitest"

beforeEach(() => {
  vi.spyOn(console, "error").mockImplementation(() => {})
})

afterEach(() => {
  document.body.innerHTML = ""
  document.head.innerHTML = ""
  localStorage.clear()
  sessionStorage.clear()
  vi.unstubAllGlobals()
  vi.resetModules()
  delete window.AuthSession
  delete window.CuidadoApi
  delete window.CuidadoConfig
  delete window.CUIDADO_API_URL
  delete window.navigateWithLoading
  delete window.showAdminAlert
})
