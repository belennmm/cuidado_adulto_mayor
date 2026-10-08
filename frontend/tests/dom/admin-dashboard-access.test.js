import { beforeEach, describe, expect, it, vi } from "vitest"

describe("acceso exclusivo al panel administrativo", () => {
  beforeEach(() => {
    document.documentElement.style.visibility = ""
    document.body.innerHTML = '<main id="adminDashboard">Panel administrativo</main>'
    window.navigateWithLoading = vi.fn()
  })

  it("muestra el panel cuando el usuario es administrador", async () => {
    window.AuthSession = {
      getToken: vi.fn(() => "admin-token"),
      getUser: vi.fn(() => ({ id: 1, role: "admin" })),
    }

    await import("../../js/auth-guard-admin.js")

    expect(window.AuthSession.getToken).toHaveBeenCalledWith(["admin", "administrador"])
    expect(window.AuthSession.getUser).toHaveBeenCalledWith(["admin", "administrador"])
    expect(document.documentElement.style.visibility).toBe("")
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
  })

  it.each([
    ["profesional", "../cuidador-profesional/home-page.html"],
    ["familiar", "../cuidador-familiar/home-page.html"],
  ])("oculta el panel y redirige al usuario %s", async (role, destination) => {
    window.AuthSession = {
      getToken: vi.fn(() => `${role}-token`),
      getUser: vi.fn(() => ({ id: 2, role })),
    }

    await import("../../js/auth-guard-admin.js")

    expect(document.documentElement.style.visibility).toBe("hidden")
    expect(window.navigateWithLoading).toHaveBeenCalledWith(destination)
  })

  it("oculta el panel y redirige al login cuando no existe sesión", async () => {
    window.AuthSession = {
      getToken: vi.fn(() => ""),
      getUser: vi.fn(() => null),
    }

    await import("../../js/auth-guard-admin.js")

    expect(document.documentElement.style.visibility).toBe("hidden")
    expect(window.navigateWithLoading).toHaveBeenCalledWith("../../index.html")
  })
})
