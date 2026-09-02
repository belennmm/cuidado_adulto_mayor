import { beforeEach, describe, expect, it, vi } from "vitest"

describe("acceso a página protegida sin sesión", () => {
  beforeEach(() => {
    window.navigateWithLoading = vi.fn()
  })

  it.each([
    ["administrativa", "../../js/auth-guard-admin.js", false],
    ["profesional", "../../js/auth-guard-profesional.js", true],
    ["familiar", "../../js/auth-guard-familiar.js", true],
  ])("redirige al inicio de sesión al cargar una página %s sin sesión", async (_area, guardPath, requiresDomReady) => {
    await import("../../js/auth-session.js")
    await import(guardPath)

    if (requiresDomReady) {
      document.dispatchEvent(new Event("DOMContentLoaded"))
    }

    expect(window.navigateWithLoading).toHaveBeenCalledWith("../../index.html")
  })

  it("permite cargar la página administrativa cuando la sesión es válida", async () => {
    document.documentElement.style.visibility = "hidden"
    await import("../../js/auth-session.js")
    window.AuthSession.saveSession("admin-token", { id: 1, role: "admin" })
    await import("../../js/auth-guard-admin.js")

    expect(window.navigateWithLoading).not.toHaveBeenCalled()
    expect(document.documentElement.style.visibility).toBe("")
  })

  it("mantiene la página administrativa oculta hasta redirigir cuando falta el token", async () => {
    await import("../../js/auth-session.js")
    await import("../../js/auth-guard-admin.js")

    expect(document.documentElement.style.visibility).toBe("hidden")
  })
})
