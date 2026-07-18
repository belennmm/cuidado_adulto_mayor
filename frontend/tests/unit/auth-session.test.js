import { beforeEach, describe, expect, it } from "vitest"

describe("AuthSession", () => {
  beforeEach(async () => {
    await import("../../js/auth-session.js")
  })

  it("guarda y recupera una sesión según el rol", () => {
    const user = { id: 1, name: "Ana", role: "profesional" }

    expect(window.AuthSession.saveSession("abc123", user)).toBe(true)
    expect(window.AuthSession.getToken(["profesional"])).toBe("abc123")
    expect(window.AuthSession.getUser(["profesional"])).toEqual(user)
  })

  it("no devuelve una sesión para un rol diferente", () => {
    window.AuthSession.saveSession("admin-token", { id: 1, role: "admin" })

    expect(window.AuthSession.getSession(["familiar"])).toBeNull()
  })

  it("elimina la sesión activa", () => {
    window.AuthSession.saveSession("abc123", { id: 1, role: "familiar" })
    window.AuthSession.clearSession("familiar")

    expect(window.AuthSession.getToken(["familiar"])).toBe("")
    expect(sessionStorage.length).toBe(0)
  })
})
