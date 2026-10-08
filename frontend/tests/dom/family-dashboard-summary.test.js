import { describe, expect, it, vi } from "vitest"

function renderDashboard() {
  document.body.innerHTML = `
    <span id="dashboardDate"></span><span id="dashboardAdultsCount"></span><span id="dashboardMedsCount"></span>
    <span id="dashboardPendingCount"></span><span id="dashboardIncidentsCount"></span>
    <span id="dashboardStableCount"></span><span id="dashboardAttentionCount"></span><span id="dashboardCriticalCount"></span>
    <div id="dashboardRoutineList"></div><div id="dashboardIncidentsList"></div><div id="dashboardAdultsList"></div>
  `
}

describe("resumen del panel familiar", () => {
  it("renderiza el resumen, las rutinas inmediatas y los incidentes", async () => {
    renderDashboard()
    window.CuidadoApi = {
      fetchJson: vi.fn().mockResolvedValue({
        date: "2026-09-23",
        stats: { older_adults: 1, medications_today: 3, pending_medications: 1, incidents_today: 1, stable: 1, attention: 0, critical: 0 },
        next_medications: [{ medication_name: "Losartán", older_adult_name: "Rosa Martínez", schedule: "08:00", dosage: "1 tableta", administered_today: false }],
        incidents: [{ title: "Presión elevada", adult_name: "Rosa Martínez", severity: "alta", incident_time: "09:30", status: "abierto" }],
        older_adults: [{ full_name: "Rosa Martínez", age: 82, room: "12", status: "Estable" }],
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/cuidador-familiar/family-api.js")
    await import("../../js/cuidador-familiar/dashboard.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))
    await Promise.resolve()
    await Promise.resolve()
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(document.getElementById("dashboardMedsCount").textContent).toBe("3")
    expect(document.getElementById("dashboardPendingCount").textContent).toBe("1")
    expect(document.getElementById("dashboardIncidentsCount").textContent).toBe("1")
    expect(document.getElementById("dashboardRoutineList").textContent).toContain("Losartán")
    expect(document.getElementById("dashboardRoutineList").textContent).toContain("Pendiente")
    expect(document.getElementById("dashboardIncidentsList").textContent).toContain("Presión elevada")
    expect(document.querySelector("#dashboardIncidentsList .badge").classList.contains("severity-high")).toBe(true)
  })
})
