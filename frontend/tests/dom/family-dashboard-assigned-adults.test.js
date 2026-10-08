import { describe, expect, it, vi } from "vitest"

function renderDashboard() {
  document.body.innerHTML = `
    <span id="dashboardDate"></span><span id="dashboardAdultsCount"></span><span id="dashboardMedsCount"></span>
    <span id="dashboardPendingCount"></span><span id="dashboardIncidentsCount"></span>
    <span id="dashboardStableCount"></span><span id="dashboardAttentionCount"></span><span id="dashboardCriticalCount"></span>
    <div id="dashboardRoutineList"></div><div id="dashboardIncidentsList"></div><div id="dashboardAdultsList"></div>
  `
}

async function loadFamilyDashboard(response) {
  window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue(response) }
  await import("../../js/ui-utils.js")
  await import("../../js/cuidador-familiar/family-api.js")
  await import("../../js/cuidador-familiar/dashboard.js")
  document.dispatchEvent(new Event("DOMContentLoaded"))
  await Promise.resolve()
  await Promise.resolve()
}

describe("adultos asignados del panel familiar", () => {
  it("consulta y muestra la información simulada de los adultos asignados", async () => {
    renderDashboard()
    await loadFamilyDashboard({
      stats: { older_adults: 2 },
      older_adults: [
        { id: 7, full_name: "Rosa Martínez", age: 82, room: "12", status: "Estable" },
        { id: 9, full_name: "Carlos López", age: 79, room: "B-4", status: "Atención" },
      ],
    })

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/family/overview", expect.objectContaining({
      expectedRoles: ["familiar", "cuidador_familiar"],
    }))
    expect(document.getElementById("dashboardAdultsCount").textContent).toBe("2")
    expect(document.querySelectorAll("#dashboardAdultsList .mini-row")).toHaveLength(2)
    expect(document.getElementById("dashboardAdultsList").textContent).toContain("Rosa Martínez")
    expect(document.getElementById("dashboardAdultsList").textContent).toContain("Carlos López")
  })
})
