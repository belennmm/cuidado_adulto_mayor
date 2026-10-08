import { describe, expect, it, vi } from "vitest"

function renderDashboard() {
  document.body.innerHTML = `
    <span id="dashboardDate"></span><span id="dashboardAdultsCount"></span><span id="dashboardMedsCount"></span>
    <span id="dashboardPendingCount"></span><span id="dashboardIncidentsCount"></span>
    <span id="dashboardStableCount"></span><span id="dashboardAttentionCount"></span><span id="dashboardCriticalCount"></span>
    <div id="dashboardRoutineList"></div><div id="dashboardIncidentsList"></div><div id="dashboardAdultsList"></div>
  `
}

describe("panel familiar sin asignaciones", () => {
  it("muestra estados vacíos cuando el familiar no tiene adultos asignados", async () => {
    renderDashboard()
    window.CuidadoApi = {
      fetchJson: vi.fn().mockResolvedValue({
        stats: { older_adults: 0, medications_today: 0, pending_medications: 0, incidents_today: 0, stable: 0, attention: 0, critical: 0 },
        older_adults: [], next_medications: [], incidents: [],
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/cuidador-familiar/family-api.js")
    await import("../../js/cuidador-familiar/dashboard.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))
    await Promise.resolve()
    await Promise.resolve()
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(document.getElementById("dashboardAdultsCount").textContent).toBe("0")
    expect(document.getElementById("dashboardAdultsList").textContent).toContain("No tienes adultos mayores asignados por ahora.")
    expect(document.getElementById("dashboardRoutineList").textContent).toContain("No hay medicamentos programados para hoy.")
    expect(document.getElementById("dashboardIncidentsList").textContent).toContain("No hay incidentes para tus familiares hoy.")
  })
})
