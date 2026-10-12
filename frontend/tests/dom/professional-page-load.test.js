import { describe, expect, it, vi } from "vitest"

const professionalRoles = ["profesional", "cuidador_profesional"]

describe("preparación de la página profesional", () => {
  it("crea una sesión simulada para un profesional", async () => {
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ user: { id: 14, role: "profesional" } }) }
    await import("../../js/auth-session.js")
    const professional = { id: 14, name: "María", role: "profesional" }

    expect(window.AuthSession.saveSession(professional)).toBe(true)
    expect(window.AuthSession.hasSession(professionalRoles)).toBe(true)
    expect(window.AuthSession.getUser(professionalRoles)).toEqual(professional)
  })

  it("carga la guardia profesional y consulta la sesión simulada", async () => {
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ user: { id: 14, role: "profesional" } }) }
    await import("../../js/auth-session.js")
    window.AuthSession.saveSession({ id: 14, role: "profesional" })
    const hasSession = vi.spyOn(window.AuthSession, "hasSession")
    const getUser = vi.spyOn(window.AuthSession, "getUser")

    await import("../../js/auth-guard-profesional.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/me")
    expect(window.AuthSession.getUser(professionalRoles)).toMatchObject({role: "profesional"})
  })

  it("mantiene disponible el contenido protegido para un profesional autenticado", async () => {
    document.body.innerHTML = '<main id="professionalContent">Panel profesional</main>'
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ user: { id: 14, role: "profesional" } }) }
    await import("../../js/auth-session.js")
    window.AuthSession.saveSession({ id: 14, role: "profesional" })

    await import("../../js/auth-guard-profesional.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))

    expect(document.getElementById("professionalContent")?.textContent).toBe("Panel profesional")
  })

  it("no redirige al profesional cuando su sesión es válida", async () => {
    window.navigateWithLoading = vi.fn()
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ user: { id: 14, role: "profesional" } }) }
    await import("../../js/auth-session.js")
    window.AuthSession.saveSession({ id: 14, role: "profesional" })

    await import("../../js/auth-guard-profesional.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))

    expect(window.navigateWithLoading).not.toHaveBeenCalled()
  })
})
