import { describe, expect, it, vi } from "vitest"

describe("guardia de rol familiar", () => {
  it("mantiene el panel disponible para una sesión válida de cuidador familiar", async () => {
    document.body.innerHTML = '<main id="familyContent">Panel familiar</main>'
    window.navigateWithLoading = vi.fn()

    await import("../../js/auth-session.js")
    window.AuthSession.saveSession("family-token", { id: 22, name: "Ana", role: "cuidador_familiar" })
    const getToken = vi.spyOn(window.AuthSession, "getToken")
    const getUser = vi.spyOn(window.AuthSession, "getUser")

    await import("../../js/auth-guard-familiar.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))

    expect(getToken).toHaveBeenCalledWith(["familiar", "cuidador_familiar"])
    expect(getUser).toHaveBeenCalledWith(["familiar", "cuidador_familiar"])
    expect(document.getElementById("familyContent").textContent).toBe("Panel familiar")
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
  })
})
