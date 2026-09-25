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
    expect(window.AuthSession.getUser(["familiar"])).toBeNull()
    expect(localStorage.getItem("cuidado.auth.cuidador_familiar.token")).toBeNull()
    expect(localStorage.getItem("cuidado.auth.cuidador_familiar.user")).toBeNull()
    expect(sessionStorage.length).toBe(0)
  })

  it("elimina datos incompletos o corruptos al validar la sesión", () => {
    localStorage.setItem("cuidado.auth.admin.token", "admin-token")
    localStorage.setItem("cuidado.auth.admin.user", "{bad-json")
    localStorage.setItem("cuidado.auth.activeRole", "admin")
    sessionStorage.setItem("cuidado.auth.tab.token", "tab-token")
    sessionStorage.setItem("cuidado.auth.tab.user", "{bad-json")
    sessionStorage.setItem("cuidado.auth.tab.role", "admin")
    localStorage.setItem("token", "legacy-token")
    localStorage.setItem("user", "{bad-json")

    expect(window.AuthSession.getSession(["admin"])).toBeNull()

    expect(localStorage.getItem("cuidado.auth.admin.token")).toBeNull()
    expect(localStorage.getItem("cuidado.auth.admin.user")).toBeNull()
    expect(localStorage.getItem("cuidado.auth.activeRole")).toBeNull()
    expect(localStorage.getItem("token")).toBeNull()
    expect(localStorage.getItem("user")).toBeNull()
    expect(sessionStorage.length).toBe(0)
  })
})
