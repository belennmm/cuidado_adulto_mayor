import { describe, expect, it, vi } from "vitest"

const professionalRoles = ["profesional", "cuidador_profesional"]

describe("preparación de la página profesional", () => {
  it("crea una sesión simulada para un profesional", async () => {
    await import("../../js/auth-session.js")
    const professional = { id: 14, name: "María", role: "profesional" }

    expect(window.AuthSession.saveSession("professional-token", professional)).toBe(true)
    expect(window.AuthSession.getToken(professionalRoles)).toBe("professional-token")
    expect(window.AuthSession.getUser(professionalRoles)).toEqual(professional)
  })

  it("carga la guardia profesional y consulta la sesión simulada", async () => {
    await import("../../js/auth-session.js")
    window.AuthSession.saveSession("professional-token", { id: 14, role: "profesional" })
    const getToken = vi.spyOn(window.AuthSession, "getToken")
    const getUser = vi.spyOn(window.AuthSession, "getUser")

    await import("../../js/auth-guard-profesional.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))

    expect(getToken).toHaveBeenCalledWith(professionalRoles)
    expect(getUser).toHaveBeenCalledWith(professionalRoles)
  })
})
