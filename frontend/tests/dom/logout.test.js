import { beforeEach, describe, expect, it, vi } from "vitest"

describe("cierre de sesion del servidor", () => {
  beforeEach(async () => {
    document.body.innerHTML = '<div id="logoutModal" class="active"><button id="confirmLogout">Salir</button></div>'
    window.AuthSession = { clearSession: vi.fn() }
    window.CuidadoApi = { fetchJson: vi.fn() }
    window.navigateWithLoading = vi.fn()
    window.showAppAlert = vi.fn()
    await import("../../js/logout-modal.js")
  })

  it("espera revocacion del servidor antes de limpiar y navegar", async () => {
    let resolve
    window.CuidadoApi.fetchJson.mockReturnValue(new Promise(done => { resolve = done }))
    document.getElementById("confirmLogout").click()
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/logout", { method: "POST" })
    expect(window.AuthSession.clearSession).not.toHaveBeenCalled()
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
    resolve({})
    await vi.waitFor(() => expect(window.navigateWithLoading).toHaveBeenCalledWith("../../index.html"))
    expect(window.AuthSession.clearSession).toHaveBeenCalledOnce()
  })

  it("mantiene visible la sesion y permite reintentar si falla el logout", async () => {
    window.CuidadoApi.fetchJson.mockRejectedValue(Object.assign(new Error("CSRF expiro"), { status: 419 }))
    document.getElementById("confirmLogout").click()
    await vi.waitFor(() => expect(window.showAppAlert).toHaveBeenCalled())
    expect(window.AuthSession.clearSession).not.toHaveBeenCalled()
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
    expect(document.getElementById("confirmLogout").disabled).toBe(false)
  })
})
