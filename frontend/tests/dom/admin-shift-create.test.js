import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function renderShiftsPage() {
  document.body.innerHTML = `
    <form id="shiftForm"><select id="caregiverSelect"></select><select id="daySelect"><option value="1" selected>Lunes</option></select><input id="startTime"><input id="endTime"><input id="shiftNotes"><button type="submit">Asignar</button></form>
    <p id="shiftMessage"></p><div id="shiftsTableBody"></div><div id="vacationsTableBody"></div>
  `
}

describe("asignación de turnos", () => {
  it("crea un turno para el cuidador seleccionado y actualiza el listado", async () => {
    renderShiftsPage()
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      fetchJson: vi.fn((path, options = {}) => {
        if (options.method === "POST") return Promise.resolve({ message: "Turno asignado correctamente." })
        if (path === "/admin/professional-caregivers") return Promise.resolve({ users: [{ id: 6, name: "Marta", email: "marta@example.test" }] })
        if (path === "/admin/schedules") return Promise.resolve({ schedules: [] })
        return Promise.resolve({ vacation_requests: [] })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/admin/shifts-view.js")
    await import("../../js/admin/shifts.js")
    await flushRequests()
    document.getElementById("caregiverSelect").value = "6"
    document.getElementById("startTime").value = "08:00"
    document.getElementById("endTime").value = "16:00"
    document.getElementById("shiftNotes").value = "Turno de mañana"
    document.getElementById("shiftForm").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/schedules", expect.objectContaining({
      method: "POST",
      body: expect.stringContaining('"user_id":6'),
    }))
    expect(window.showAdminAlert).toHaveBeenCalledWith("Turno asignado correctamente.", { variant: "success" })
    expect(document.getElementById("shiftMessage").textContent).toContain("Turno asignado correctamente.")
  })
})
