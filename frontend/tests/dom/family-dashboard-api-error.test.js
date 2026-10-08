import { describe, expect, it, vi } from "vitest"

function renderDashboard() {
  document.body.innerHTML = `
    <span id="dashboardDate"></span><span id="dashboardAdultsCount"></span><span id="dashboardMedsCount"></span>
    <span id="dashboardPendingCount"></span><span id="dashboardIncidentsCount"></span>
    <span id="dashboardStableCount"></span><span id="dashboardAttentionCount"></span><span id="dashboardCriticalCount"></span>
    <div id="dashboardRoutineList"></div><div id="dashboardIncidentsList"></div><div id="dashboardAdultsList"></div>
  `
}

describe("error de API en el panel familiar", () => {
  it("presenta el mensaje de error en las secciones que no pudieron cargarse", async () => {
    renderDashboard()
    window.CuidadoApi = { fetchJson: vi.fn().mockRejectedValue(new Error("No se pudo cargar la información.")) }

    await import("../../js/ui-utils.js")
    await import("../../js/cuidador-familiar/family-api.js")
    await import("../../js/cuidador-familiar/dashboard.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))
    await Promise.resolve()
    await Promise.resolve()
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/family/overview", expect.objectContaining({
      expectedRoles: ["familiar", "cuidador_familiar"],
    }))
    ;["dashboardRoutineList", "dashboardIncidentsList", "dashboardAdultsList"].forEach((id) => {
      expect(document.getElementById(id).textContent).toContain("No se pudo cargar la información.")
    })
  })
})
