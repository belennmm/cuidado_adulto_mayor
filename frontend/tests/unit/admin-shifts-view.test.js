import { beforeEach, describe, expect, it } from "vitest"

const escapeHtml = (value) => String(value ?? "").replaceAll("<", "&lt;").replaceAll(">", "&gt;")

describe("AdminShiftsView", () => {
  let state
  let caregiverSelect
  let shiftsTableBody
  let vacationsTableBody
  let view

  beforeEach(async () => {
    document.body.innerHTML = '<select id="caregivers"></select><div id="shifts"></div><div id="vacations"></div>'
    caregiverSelect = document.getElementById("caregivers")
    shiftsTableBody = document.getElementById("shifts")
    vacationsTableBody = document.getElementById("vacations")
    state = { caregivers: [], schedules: [], vacations: [] }
    await import("../../js/admin/shifts-view.js")
    view = window.AdminShiftsView.create({
      state,
      caregiverSelect,
      shiftsTableBody,
      vacationsTableBody,
      DAY_LABELS: { 1: "Lunes" },
      escapeHtml,
      formatDate: (value) => value,
      formatTimeRange: (schedule) => `${schedule.start_time} - ${schedule.end_time}`,
      statusLabel: (status) => status === "pending" ? "Pendiente" : "Aprobada",
    })
  })

  it("presenta cuidadores aprobados en el selector", () => {
    state.caregivers = [{ id: 7, name: "Ana", email: "ana@example.test" }]
    view.renderCaregiverOptions()
    expect(caregiverSelect.options).toHaveLength(2)
    expect(caregiverSelect.options[1].value).toBe("7")
    expect(caregiverSelect.options[1].textContent).toContain("Ana")
  })

  it("muestra el estado vacío de turnos", () => {
    view.renderSchedules()
    expect(shiftsTableBody.textContent).toContain("Todavía no hay turnos asignados")
  })

  it("renderiza solicitudes de vacaciones pendientes y escapa contenido", () => {
    state.vacations = [{ id: 3, status: "pending", start_date: "2026-09-21", end_date: "2026-09-22", reason: "<script>", user: { name: "Luis", email: "luis@example.test" } }]
    view.renderVacations()
    expect(vacationsTableBody.querySelector(".approve-vacation-button")?.dataset.id).toBe("3")
    expect(vacationsTableBody.textContent).toContain("<script>")
    expect(vacationsTableBody.querySelector("script")).toBeNull()
  })
})
