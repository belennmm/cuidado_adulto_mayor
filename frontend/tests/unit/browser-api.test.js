import { beforeEach, describe, expect, it, vi } from "vitest"

const response = (data = {}, status = 200) => ({ ok: status < 400, status, headers: { get: () => "application/json" }, json: async () => data })
const removeCsrf = () => { document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/" }

describe("API del navegador con cookies y CSRF", () => {
  beforeEach(async () => {
    await import("../../js/api-config.js")
    window.AuthSession = { clearSession: vi.fn() }
  })

  it("inicializa CSRF antes de login, incluye cookies y no envia Bearer", async () => {
    removeCsrf()
    const fetchMock = vi.fn().mockImplementation(async url => {
      if (url.endsWith("/csrf-cookie")) document.cookie = "XSRF-TOKEN=encrypted%2Bcsrf; path=/"
      return response({ user: { id: 1, role: "admin" } })
    })
    vi.stubGlobal("fetch", fetchMock)
    await window.CuidadoApi.fetchJson("/login", { method: "POST", body: "{}", credentials: "omit", headers: { Authorization: "Bearer forged" } })
    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual(["/api/csrf-cookie", "/api/login"])
    expect(fetchMock.mock.calls[1][1]).toMatchObject({ credentials: "include", redirect: "error", headers: { "x-xsrf-token": "encrypted+csrf", "x-cuidado-session": "1" } })
    expect(fetchMock.mock.calls[1][1].headers).not.toHaveProperty("authorization")
  })

  it("renueva CSRF al iniciar sesion aunque sobreviva una cookie antigua", async () => {
    const fetchMock = vi.fn().mockImplementation(async url => {
      if (url.endsWith("/csrf-cookie")) document.cookie = "XSRF-TOKEN=new-token; path=/"
      return response()
    })
    vi.stubGlobal("fetch", fetchMock)
    await window.CuidadoApi.fetchJson("/login", { method: "POST" })
    expect(fetchMock.mock.calls[1][1].headers["x-xsrf-token"]).toBe("new-token")
  })

  it("comparte la inicializacion CSRF entre escrituras simultaneas", async () => {
    removeCsrf()
    vi.stubGlobal("fetch", vi.fn().mockImplementation(async url => {
      if (url.endsWith("/csrf-cookie")) document.cookie = "XSRF-TOKEN=csrf; path=/"
      return response()
    }))
    await Promise.all([window.CuidadoApi.fetchJson("/me", { method: "PUT" }), window.CuidadoApi.fetchJson("/logout", { method: "POST" })])
    expect(fetch.mock.calls.filter(([url]) => url.endsWith("/csrf-cookie"))).toHaveLength(1)
  })

  it("no realiza la escritura cuando el navegador no acepta cookies CSRF", async () => {
    removeCsrf()
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(response()))
    await expect(window.CuidadoApi.fetchJson("/me", { method: "PUT" })).rejects.toThrow("cookies")
    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it("limpia metadatos con 401 y no repite escrituras rechazadas con 419", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValueOnce(response({}, 401)).mockResolvedValueOnce(response({}, 419)))
    await expect(window.CuidadoApi.fetchJson("/me")).rejects.toMatchObject({ status: 401 })
    expect(window.AuthSession.clearSession).toHaveBeenCalledOnce()
    await expect(window.CuidadoApi.fetchJson("/me", { method: "PUT" })).rejects.toMatchObject({ status: 419 })
    expect(fetch).toHaveBeenCalledTimes(2)
  })
})
