import { beforeEach, describe, expect, it, vi } from "vitest"

describe("ProfessionalRoutinesService", () => {
  let fetchJson

  beforeEach(async () => {
    fetchJson = vi.fn().mockResolvedValue({})
    window.ProfessionalCare = { fetchJson }
    await import("../../js/cuidador-profesional/routines-service.js")
  })

  it("carga los tres recursos del panel", async () => {
    await window.ProfessionalRoutinesService.loadDashboard(12)
    expect(fetchJson.mock.calls.map(([path]) => path)).toEqual([
      "/professional/routines?older_adult_id=12",
      "/professional/routine-notes?older_adult_id=12",
      "/rutinas?older_adult_id=12",
    ])
  })

  it("crea rutinas incluyendo el adulto mayor", async () => {
    await window.ProfessionalRoutinesService.saveRoutine({
      olderAdultId: 4, nombre: "Movilidad", horario: "08:30", actividades: ["Caminar"],
    })
    expect(fetchJson).toHaveBeenCalledWith("/rutinas", expect.objectContaining({ method: "POST" }))
    expect(JSON.parse(fetchJson.mock.calls[0][1].body)).toMatchObject({ adulto_mayor_id: 4 })
  })

  it("actualiza notas sin reenviar el adulto mayor", async () => {
    await window.ProfessionalRoutinesService.saveNote({ id: 8, olderAdultId: 4, content: "Estable" })
    expect(fetchJson).toHaveBeenCalledWith("/professional/routine-notes/8", expect.objectContaining({ method: "PUT" }))
    expect(JSON.parse(fetchJson.mock.calls[0][1].body)).toEqual({ content: "Estable" })
  })
})
