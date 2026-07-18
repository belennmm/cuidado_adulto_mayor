import { beforeEach, describe, expect, it, vi } from "vitest"

describe("CuidadoApi", () => {
  beforeEach(async () => {
    window.CUIDADO_API_URL = "https://api.example.test/api/"
    await import("../../js/api-config.js")
  })

  it("construye URLs relativas y conserva URLs absolutas", () => {
    expect(window.CuidadoApi.buildUrl("/login")).toBe("https://api.example.test/api/login")
    expect(window.CuidadoApi.buildUrl("https://otro.test/data")).toBe("https://otro.test/data")
  })

  it("agrega encabezados JSON y el token de autenticación", () => {
    localStorage.setItem("token", "token-prueba")
    const headers = window.CuidadoApi.getHeaders({ body: "{}" })

    expect(headers).toMatchObject({
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: "Bearer token-prueba",
    })
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

    await expect(window.CuidadoApi.fetchJson("/register", { method: "POST" }))
      .rejects.toMatchObject({ message: "El correo ya existe.", status: 422 })
  })
})
