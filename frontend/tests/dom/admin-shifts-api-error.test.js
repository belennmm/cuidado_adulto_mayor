import { describe, expect, it, vi } from "vitest"

describe("error de API en turnos", () => {
  it("muestra el error cuando no se pueden cargar los datos iniciales", async () => {
    document.body.innerHTML = `
      <form id="shiftForm"><select id="caregiverSelect"></select><select id="daySelect"><option value="1">Lunes</option></select><input id="startTime"><input id="endTime"><input id="shiftNotes"></form>
      <p id="shiftMessage"></p><div id="shiftsTableBody"></div><div id="vacationsTableBody"></div>
    `
    window.CuidadoApi = {
      fetchJson: vi.fn().mockRejectedValue(new Error("No se pudieron cargar los turnos.")),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/admin/shifts-view.js")
    await import("../../js/admin/shifts.js")
    await Promise.resolve()
    await Promise.resolve()
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/professional-caregivers", expect.objectContaining({ expectedRoles: ["admin"] }))
    expect(document.getElementById("shiftsTableBody").textContent).toContain("No se pudieron cargar los turnos.")
    expect(document.getElementById("vacationsTableBody").textContent).toContain("No se pudieron cargar los turnos.")
  })
})
