import { beforeEach, describe, expect, it, vi } from "vitest"

describe("IncidentsService", () => {
  beforeEach(async () => {
    await import("../../js/incidents-service.js")
  })

  function createService({ pathname = "/pages/admin/incidents.html", role = "admin" } = {}) {
    const api = { getToken: vi.fn(() => "token"), fetchJson: vi.fn() }
    const authSession = { getUser: vi.fn(() => ({ role })) }
    const service = window.IncidentsService.create({ api, authSession, location: { pathname } })
    return { api, service }
  }

  it("usa endpoints administrativos en el contexto de administrador", async () => {
    const { api, service } = createService()
    api.fetchJson.mockResolvedValue({ older_adults: [{ id: 1 }] })

    await expect(service.loadOlderAdults()).resolves.toEqual([{ id: 1 }])
    expect(api.fetchJson).toHaveBeenCalledWith("/admin/older-adults", expect.objectContaining({ expectedRoles: ["admin"] }))
  })

  it("usa endpoints profesionales fuera del panel administrativo", async () => {
    const { api, service } = createService({ pathname: "/pages/cuidador-profesional/incidents.html", role: "profesional" })
    api.fetchJson.mockResolvedValue({ message: "Creado" })
    const payload = { title: "Caída", older_adult_id: 2 }

    await service.createIncident(payload)
    expect(api.fetchJson).toHaveBeenCalledWith("/professional/incidents", expect.objectContaining({
      method: "POST",
      body: JSON.stringify(payload),
      expectedRoles: ["profesional", "cuidador_profesional"],
    }))
  })

  it("agrega la fecha codificada al listado de incidentes", async () => {
    const { api, service } = createService()
    api.fetchJson.mockResolvedValue({ incidents: [] })
    await service.loadIncidents("2026-09-21")
    expect(api.fetchJson).toHaveBeenCalledWith("/incidents?date=2026-09-21", expect.any(Object))
  })

  it("informa si existe una sesión válida", () => {
    const { api, service } = createService()
    expect(service.hasSession()).toBe(true)
    api.getToken.mockReturnValue(null)
    expect(service.hasSession()).toBe(false)
  })
})
