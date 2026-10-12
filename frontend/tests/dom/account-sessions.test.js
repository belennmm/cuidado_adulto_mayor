import { beforeEach, describe, expect, it, vi } from "vitest"

describe("cierre de todas las sesiones", () => {
  beforeEach(async () => {
    document.body.innerHTML = '<form id="revokeSessionsForm"><input id="revokeSessionsPassword"><button type="submit">Cerrar sesiones</button><p id="revokeSessionsMessage"></p></form>'
    window.CuidadoApi = { fetchJson: vi.fn() }
    window.AuthSession = { clearSession: vi.fn() }
    window.navigateWithLoading = vi.fn()
    await import("../../js/account-sessions.js")
  })
  function submit() { document.getElementById("revokeSessionsForm").dispatchEvent(new Event("submit", { cancelable: true })) }
  it("confirma con la contraseña exacta y espera al servidor para salir", async () => {
    let resolve
    window.CuidadoApi.fetchJson.mockReturnValue(new Promise(done => { resolve = done }))
    document.getElementById("revokeSessionsPassword").value = "  Secret!123  "
    submit()
    submit()
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledOnce()
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/logout-all", { method: "POST", body: JSON.stringify({ current_password: "  Secret!123  " }) })
    expect(window.AuthSession.clearSession).not.toHaveBeenCalled()
    resolve({})
    await vi.waitFor(() => expect(window.navigateWithLoading).toHaveBeenCalledWith("../../index.html"))
    expect(window.AuthSession.clearSession).toHaveBeenCalledOnce()
    expect(document.getElementById("revokeSessionsPassword").value).toBe("")
  })
  it("no envia una contraseña vacia", () => {
    submit()
    expect(window.CuidadoApi.fetchJson).not.toHaveBeenCalled()
    expect(document.getElementById("revokeSessionsMessage").textContent).toContain("actual")
  })
  it("muestra el rechazo y permite reintentar sin fingir un cierre", async () => {
    window.CuidadoApi.fetchJson.mockRejectedValue(new Error("Contraseña incorrecta"))
    document.getElementById("revokeSessionsPassword").value = "wrong"
    submit()
    await vi.waitFor(() => expect(document.getElementById("revokeSessionsMessage").textContent).toBe("Contraseña incorrecta"))
    expect(window.AuthSession.clearSession).not.toHaveBeenCalled()
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
    expect(document.querySelector("button").disabled).toBe(false)
    expect(document.getElementById("revokeSessionsPassword").value).toBe("")
  })
})
