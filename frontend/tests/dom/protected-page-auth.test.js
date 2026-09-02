import { beforeEach, describe, expect, it, vi } from "vitest"

describe("acceso a página protegida sin sesión", () => {
  beforeEach(() => {
    window.navigateWithLoading = vi.fn()
  })

  it("redirige al inicio de sesión cuando se carga una página administrativa sin token", async () => {
    await import("../../js/auth-session.js")
    await import("../../js/auth-guard-admin.js")

    expect(window.navigateWithLoading).toHaveBeenCalledTimes(1)
    expect(window.navigateWithLoading).toHaveBeenCalledWith("../../index.html")
    expect(document.documentElement.style.visibility).toBe("hidden")
  })
})
