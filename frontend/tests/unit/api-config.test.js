import { beforeEach, describe, expect, it, vi } from "vitest"

describe("CuidadoApi", () => {
  beforeEach(async () => {
    window.CUIDADO_API_URL = "https://api.example.test/api/"
    await import("../../js/api-config.js")
  })

  it("construye URLs y rechaza destinos externos", () => {
    expect(window.CuidadoApi.buildUrl("/login")).toBe("https://api.example.test/api/login")
    expect(() => window.CuidadoApi.buildUrl("https://otro.test/data")).toThrow("URL de API no permitida")
  })

  it("agrega encabezados JSON y el token de autenticación", () => {
    localStorage.setItem("token", "token-prueba")
    const headers = window.CuidadoApi.getHeaders({ body: "{}", headers: { Authorization: "Bearer forged" } })

    expect(headers).toMatchObject({
      accept: "application/json",
      "content-type": "application/json",
      "x-cuidado-session": "1",
      "x-xsrf-token": "test-csrf",
    })
    expect(headers).not.toHaveProperty("authorization")
  })

  it("realiza una solicitud y devuelve la respuesta JSON", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      headers: { get: () => "application/json" },
      json: async () => ({ ok: true }),
    })
    vi.stubGlobal("fetch", fetchMock)

    await expect(window.CuidadoApi.fetchJson("/ping", { auth: false })).resolves.toEqual({ ok: true })
    expect(fetchMock).toHaveBeenCalledWith("https://api.example.test/api/ping", expect.any(Object))
  })

  it("convierte errores HTTP y validaciones en un Error descriptivo", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: false,
      status: 422,
      statusText: "Unprocessable Content",
      headers: { get: () => "application/json" },
      json: async () => ({ errors: { email: ["El correo ya existe."] } }),
    }))

    await expect(window.CuidadoApi.fetchJson("/me", { method: "PUT" }))
      .rejects.toMatchObject({ message: "El correo ya existe.", status: 422 })
  })

  it("notifica la expiración de sesión desde el cliente HTTP", async () => {
    const expire = vi.fn()
    const clearSession = vi.fn()
    const expired = vi.fn()
    window.AuthSession = { clearSession, expire }
    window.addEventListener("cuidado:session-expired", expired, { once: true })
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
      statusText: "Unauthorized",
      headers: { get: () => "application/json" },
      json: async () => ({ message: "Sesión expirada." }),
    }))

    await expect(window.CuidadoApi.fetchJson("/me", { expectedRoles: ["admin"] })).rejects.toMatchObject({ status: 401 })
    expect(expire).toHaveBeenCalledOnce()
    expect(clearSession).toHaveBeenCalledOnce()
    expect(expired).toHaveBeenCalledOnce()
  })

  it("bloquea secretos en query antes de llamar al servidor", async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    for (const key of ['password', 'token', 'code', 'challenge', 'recovery_codes']) {
      await expect(window.CuidadoApi.fetchJson(`/ping?${key}=sensitive`)).rejects.toThrow('credenciales')
    }
    expect(fetchMock).not.toHaveBeenCalled()
  })
})
