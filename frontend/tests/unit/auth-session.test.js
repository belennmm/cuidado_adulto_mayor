import { beforeEach, describe, expect, it, vi } from "vitest"

describe("AuthSession con cookie del servidor", () => {
  beforeEach(async () => { await import("../../js/auth-session.js") })
  it("guarda solo metadatos de interfaz en sessionStorage", () => {
    const user = { id: 1, name: "Ana", role: "profesional" }
    expect(window.AuthSession.saveSession(user)).toBe(true)
    expect(window.AuthSession.hasSession(["profesional"])).toBe(true)
    expect(window.AuthSession.getUser(["profesional"])).toEqual(user)
    expect(window.AuthSession.getSession()).not.toHaveProperty("token")
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(1)
    expect(sessionStorage.getItem("cuidado.session.user")).toBe(JSON.stringify(user))
    expect(window.AuthSession.getSession(["familiar"])).toBeNull()
  })
  it("elimina tokens legados en ambos almacenamientos al cargar", async () => {
    localStorage.setItem("token", "secret")
    localStorage.setItem("cuidado.auth.admin.token", "secret")
    sessionStorage.setItem("cuidado.auth.tab.token", "secret")
    localStorage.setItem("theme", "dark")
    vi.resetModules()
    await import("../../js/auth-session.js")
    expect(localStorage.getItem("token")).toBeNull()
    expect(localStorage.getItem("cuidado.auth.admin.token")).toBeNull()
    expect(sessionStorage.length).toBe(0)
    expect(localStorage.getItem("theme")).toBe("dark")
  })
  it("revalida en el servidor y comparte solicitudes simultaneas", async () => {
    window.AuthSession.saveSession({ id: 999, role: "admin" })
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ user: { id: 2, role: "familiar" } }) }
    const users = await Promise.all([window.AuthSession.refresh(), window.AuthSession.refresh()])
    expect(users[0]).toEqual({ id: 2, role: "familiar" })
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledOnce()
    expect(window.AuthSession.getUser()).toEqual(users[0])
  })
  it("borra metadatos cuando el servidor rechaza la sesion", async () => {
    window.AuthSession.saveSession({ id: 1, role: "admin" })
    window.CuidadoApi = { fetchJson: vi.fn().mockRejectedValue(new Error("401")) }
    await expect(window.AuthSession.refresh()).rejects.toThrow("401")
    expect(window.AuthSession.hasSession()).toBe(false)
    expect(sessionStorage.length).toBe(0)
  })
  it("limpia la sesion local", () => {
    window.AuthSession.saveSession({ id: 1, role: "familiar" })
    window.AuthSession.clearSession()
    expect(window.AuthSession.getUser()).toBeNull()
    expect(window.AuthSession.hasSession()).toBe(false)
    expect(sessionStorage.length).toBe(0)
  })
  it("expira la sesión mediante una operación compartida e idempotente", () => {
    window.AuthSession.saveSession({ id: 1, role: "admin" })
    expect(window.AuthSession.expire()).toBe(true)
    expect(window.AuthSession.expire()).toBe(false)
    expect(window.AuthSession.getUser()).toBeNull()
  })
})
