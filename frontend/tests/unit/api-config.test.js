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
      json: async () => ({ code: "validation_failed", message: "Los datos enviados no son válidos.", errors: { email: ["El correo ya existe."] } }),
    }))

    await expect(window.CuidadoApi.fetchJson("/me", { method: "PUT" }))
      .rejects.toMatchObject({ message: "El correo ya existe.", status: 422, code: "validation_failed", errors: { email: ["El correo ya existe."] } })
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

  it.each([
    ["admin", "/admin/older-adults", "POST"],
    ["profesional", "/professional/incidents", "POST"],
    ["familiar", "/me", "PUT"],
  ])("identifica el 401 de un formulario %s y ofrece volver al login", async (role, path, method) => {
    document.body.innerHTML = '<form><input name="notes" value="Texto en edición"></form>'
    window.navigateWithLoading = vi.fn()
    window.AuthSession = { expire: vi.fn(() => true), clearSession: vi.fn() }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false, status: 401, statusText: "Unauthorized",
      headers: { get: () => "application/json" },
      json: async () => ({ message: "Unauthenticated.", code: "internal_authentication_error" }),
    })
    vi.stubGlobal("fetch", fetchMock)

    await expect(window.CuidadoApi.fetchJson(path, { method, body: "{}", expectedRoles: [role] }))
      .rejects.toMatchObject({ status: 401, code: "session_expired", message: "Tu sesión ha expirado. Inicia sesión nuevamente para continuar." })

    const notice = document.getElementById("sessionExpiredNotice")
    expect(notice.getAttribute("role")).toBe("alert")
    expect(notice.textContent).toContain("Tu sesión ha expirado")
    expect(notice.textContent).not.toContain("Unauthenticated")
    expect(notice.querySelector("a").textContent).toBe("Iniciar sesión")
    expect(notice.querySelector("a").getAttribute("href")).toBe("../../index.html")
    expect(document.querySelector("input").value).toBe("Texto en edición")
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
    expect(window.AuthSession.clearSession).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it("muestra un solo aviso ante respuestas 401 concurrentes, incluso sin expectedRoles", async () => {
    window.AuthSession = { expire: vi.fn(() => false), clearSession: vi.fn() }
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: false, status: 401, headers: { get: () => "text/html" }, text: async () => "401 Unauthorized",
    }))
    await Promise.allSettled([
      window.CuidadoApi.fetchJson("/me", { method: "PUT", body: "{}" }),
      window.CuidadoApi.fetchJson("/me", { method: "PUT", body: "{}" }),
    ])
    expect(document.querySelectorAll("#sessionExpiredNotice")).toHaveLength(1)
  })

  it.each([403, 419, 422])("conserva el manejo normal del error %s sin indicar expiración", async (status) => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: false, status, headers: { get: () => "application/json" }, json: async () => ({ message: "Revisa la solicitud." }),
    }))
    await expect(window.CuidadoApi.fetchJson("/me", { method: "PUT", body: "{}" }))
      .rejects.toMatchObject({ status, message: "Revisa la solicitud.", code: "http_error" })
    expect(document.getElementById("sessionExpiredNotice")).toBeNull()
  })

  it("no interpreta credenciales incorrectas de login como expiración", async () => {
    vi.stubGlobal("fetch", vi.fn().mockImplementation(async (url) => ({
      ok: url.endsWith("/csrf-cookie"), status: url.endsWith("/csrf-cookie") ? 200 : 401,
      headers: { get: () => "application/json" }, json: async () => ({ message: "Credenciales inválidas" }),
    })))
    await expect(window.CuidadoApi.fetchJson("/login", { method: "POST", body: "{}", auth: false }))
      .rejects.toMatchObject({ status: 401, message: "Credenciales inválidas", code: "http_error" })
    expect(document.getElementById("sessionExpiredNotice")).toBeNull()
  })

  it("devuelve una escritura válida sin mostrar el aviso de expiración", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
      ok: true, status: 201, headers: { get: () => "application/json" }, json: async () => ({ id: 7 }),
    }))
    await expect(window.CuidadoApi.fetchJson("/admin/older-adults", { method: "POST", body: "{}", expectedRoles: ["admin"] }))
      .resolves.toEqual({ id: 7 })
    expect(document.getElementById("sessionExpiredNotice")).toBeNull()
  })
})
