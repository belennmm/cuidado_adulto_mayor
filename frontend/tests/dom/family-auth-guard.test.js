import { describe, expect, it, vi } from "vitest"

describe("guardia de rol familiar", () => {
  it("mantiene el panel disponible para una sesión válida de cuidador familiar", async () => {
    document.body.innerHTML = '<main id="familyContent">Panel familiar</main>'
    window.navigateWithLoading = vi.fn()

    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ user: { id: 22, role: "cuidador_familiar" } }) }
    await import("../../js/auth-session.js")
    window.AuthSession.saveSession({ id: 22, name: "Ana", role: "cuidador_familiar" })
    const hasSession = vi.spyOn(window.AuthSession, "hasSession")
    const getUser = vi.spyOn(window.AuthSession, "getUser")

    await import("../../js/auth-guard-familiar.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/me")
    expect(window.AuthSession.getUser()).toMatchObject({role: "cuidador_familiar"})
    expect(document.getElementById("familyContent").textContent).toBe("Panel familiar")
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
  })
})
