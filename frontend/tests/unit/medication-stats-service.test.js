import { beforeEach, describe, expect, it, vi } from "vitest"

describe("MedicationStatsService", () => {
  let fetchJson
  beforeEach(async () => {
    fetchJson = vi.fn().mockResolvedValue({})
    window.CuidadoApi = { fetchJson }
    await import("../../js/admin/medication-stats-service.js")
  })

  it("carga estadísticas, inventario y adultos desde sus endpoints", async () => {
    await window.MedicationStatsService.loadStatistics("month")
    await window.MedicationStatsService.loadInventory()
    await window.MedicationStatsService.loadOlderAdults()
    expect(fetchJson.mock.calls.map(([path]) => path)).toEqual([
      "/admin/medication-statistics?filter=month",
      "/admin/medications/inventory",
      "/admin/older-adults",
    ])
  })

  it("actualiza stock mediante PATCH", async () => {
    await window.MedicationStatsService.adjustStock(3, "increase", 5)
    expect(fetchJson).toHaveBeenCalledWith("/admin/medications/inventory/3/stock", expect.objectContaining({ method: "PATCH" }))
  })
})
