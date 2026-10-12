import { beforeEach, describe, expect, it, vi } from "vitest"

describe("acceso a página protegida sin sesión", () => {
  beforeEach(() => {
    window.navigateWithLoading = vi.fn()
    window.CuidadoApi = { fetchJson: vi.fn().mockRejectedValue(new Error("401")) }
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
    window.AuthSession.saveSession({ id: 1, role: "admin" })
    window.CuidadoApi.fetchJson.mockResolvedValue({user: { id: 1, role: "admin" }})
    await import("../../js/auth-guard-admin.js")

    expect(window.navigateWithLoading).not.toHaveBeenCalled()
    expect(document.documentElement.style.visibility).toBe("")
  })

  it("redirige al usuario con rol incorrecto hacia su área permitida", async () => {
    window.AuthSession = {
      hasSession: vi.fn(() => "professional-token"),
      getUser: vi.fn(() => ({ id: 2, role: "profesional" })),
      refresh: vi.fn().mockResolvedValue({ id: 2, role: "profesional" }),
    }

    await import("../../js/auth-guard-admin.js")

    expect(window.AuthSession.refresh).toHaveBeenCalledOnce()
    expect(window.navigateWithLoading).toHaveBeenCalledWith("../cuidador-profesional/home-page.html")
  })

  it("mantiene oculto el contenido administrativo cuando el rol no está permitido", async () => {
    document.documentElement.style.visibility = ""
    document.body.innerHTML = '<main id="adminContent">Panel administrativo</main>'
    window.AuthSession = {
      hasSession: vi.fn(() => "family-token"),
      getUser: vi.fn(() => ({ id: 3, role: "familiar" })),
      refresh: vi.fn().mockResolvedValue({ id: 3, role: "familiar" }),
    }

    await import("../../js/auth-guard-admin.js")

    expect(window.navigateWithLoading).toHaveBeenCalledWith("../cuidador-familiar/home-page.html")
    expect(document.documentElement.style.visibility).toBe("hidden")
  })

  it("mantiene la página administrativa oculta hasta redirigir mientras el servidor verifica la sesion", async () => {
    await import("../../js/auth-session.js")
    await import("../../js/auth-guard-admin.js")

    expect(document.documentElement.style.visibility).toBe("hidden")
  })

  it("limpia una sesión corrupta y redirige al login", async () => {
    localStorage.setItem("cuidado.auth.admin.token", "admin-token")
    localStorage.setItem("cuidado.auth.admin.user", "{bad-json")
    localStorage.setItem("cuidado.auth.activeRole", "admin")
    sessionStorage.setItem("cuidado.auth.tab.token", "tab-token")
    sessionStorage.setItem("cuidado.auth.tab.user", "{bad-json")
    sessionStorage.setItem("cuidado.auth.tab.role", "admin")

    await import("../../js/auth-session.js")
    await import("../../js/auth-guard-admin.js")

    expect(window.navigateWithLoading).toHaveBeenCalledWith("../../index.html")
    expect(localStorage.getItem("cuidado.auth.admin.token")).toBeNull()
    expect(localStorage.getItem("cuidado.auth.admin.user")).toBeNull()
    expect(localStorage.getItem("cuidado.auth.activeRole")).toBeNull()
    expect(sessionStorage.length).toBe(0)
  })
})
