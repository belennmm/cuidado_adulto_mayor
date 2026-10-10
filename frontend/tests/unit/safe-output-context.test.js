import { beforeEach, describe, expect, it, vi } from "vitest"

const payload = '\"><img src=x onerror="window.injected=true"><script>alert(1)</script>&amp;'

function expectInert(target) {
  expect(target.querySelector("img, script, svg, iframe")).toBeNull()
  expect(target.querySelector("[onerror], [onclick], [onload]")).toBeNull()
}

describe("salida según contexto", () => {
  beforeEach(async () => {
    await import("../../js/ui-utils.js")
  })

  it("inserta texto literal sin interpretar ni escapar dos veces", () => {
    const node = window.CuidadoUi.element("p", "message", payload)
    expect(node.textContent).toBe(payload)
    expectInert(node)
    expect(node.childNodes).toHaveLength(1)
  })

  it("preserva nombres, dosis y notas como valores de formulario", async () => {
    document.body.innerHTML = '<div id="medicines"></div>'
    await import("../../js/form-utils.js")
    await import("../../js/admin/older-adult-form.js")
    const list = document.getElementById("medicines")
    const manager = window.OlderAdultForm.createMedicineManager(list)
    manager.add({ id: payload, name: payload, dosage: payload, schedule: payload, notes: payload, days: ["lunes"] })
    expectInert(list)
    expect(list.querySelector("input[type=text]").value).toBe(payload)
    expect(list.querySelector("textarea").value).toBe(payload)
    expect(list.querySelector(".medicine-card").dataset.medicationAssignmentId).toBe(payload)
    expect(manager.read()[0]).toMatchObject({ name: payload, dosage: payload, schedule: payload, notes: payload, days: ["lunes"] })
  })

  it("mantiene identificadores y contenido de notas como datos", async () => {
    document.body.innerHTML = '<div id="professionalRoutineNotesList"></div><span id="professionalWeeklyNotesCount"></span>'
    window.ProfessionalCare = { formatShortDate: () => payload }
    await import("../../js/cuidador-profesional/routines-view.js")
    window.ProfessionalRoutinesView.renderNotes([{ id: payload, content: payload, professional_caregiver: { name: payload } }])
    const list = document.getElementById("professionalRoutineNotesList")
    expectInert(list)
    expect(list.querySelector("p").textContent).toBe(payload)
    expect(list.querySelector("button").dataset.id).toBe(payload)
    expect(list.querySelector("article").dataset.noteId).toBe(payload)
  })

  it("mantiene las acciones de rutinas sin convertir los IDs en atributos ejecutables", async () => {
    document.body.innerHTML = '<div id="professionalCustomRoutinesList"></div><span id="professionalCustomRoutineTotal"></span>'
    await import("../../js/cuidador-profesional/routines-view.js")
    window.ProfessionalRoutinesView.renderCustomRoutines([{ id: payload, nombre: payload, horario: payload, actividades: [payload] }])
    const list = document.getElementById("professionalCustomRoutinesList")
    expectInert(list)
    expect(list.querySelector("li strong").textContent).toBe(payload)
    expect(list.querySelector('[data-custom-routine-action="complete"]').dataset.id).toBe(payload)
    expect(list.querySelector('[data-custom-routine-action="complete"]').dataset.activityIndex).toBe("0")
  })

  it("busca la nota por igualdad del dato sin interpolarla en un selector CSS", async () => {
    document.body.innerHTML = '<div id="professionalRoutineNotesList"></div><textarea id="routineNoteInput"></textarea><form id="professionalRoutineNoteForm"></form>'
    const card = window.CuidadoUi.element("article", "", null, [window.CuidadoUi.element("p", "", payload)])
    card.dataset.noteId = payload
    document.getElementById("professionalRoutineNotesList").append(card)
    await import("../../js/cuidador-profesional/routines-actions.js")
    const state = {}
    const actions = window.ProfessionalRoutinesActions.create({ state, setMessage: vi.fn() })
    actions.startEditNote(payload)
    expect(state.editingNoteId).toBe(payload)
    expect(document.getElementById("routineNoteInput").value).toBe(payload)
  })

  it("renderiza turnos y vacaciones con texto y callbacks conservados", async () => {
    await import("../../js/admin/shifts-view.js")
    const shifts = document.createElement("div")
    const vacations = document.createElement("div")
    const resolve = vi.fn()
    const view = window.AdminShiftsView.create({
      state: {
        caregivers: [],
        schedules: [{ id: payload, user: { name: payload }, notes: payload, change_request: { status: "pending", message: payload, start_time: "09:00", end_time: "17:00" } }],
        vacations: [{ id: payload, user: { name: payload }, reason: payload, status: "pending" }],
      },
      shiftsTableBody: shifts, vacationsTableBody: vacations,
      DAY_LABELS: {}, formatDate: () => payload, formatTimeRange: () => payload, statusLabel: () => payload,
      resolveChangeRequest: resolve, resolveVacationRequest: resolve,
    })
    view.renderSchedules()
    view.renderVacations()
    expectInert(shifts)
    expectInert(vacations)
    expect(shifts.querySelector(".request-card p").textContent).toBe(payload)
    shifts.querySelector(".approve-request-button").click()
    vacations.querySelector(".reject-vacation-button").click()
    expect(resolve).toHaveBeenCalledWith(payload, "approve")
    expect(resolve).toHaveBeenCalledWith(payload, "reject")
  })

  it.each(["renderMonthView", "renderWeekView", "renderDayView"])("%s construye los turnos sin analizar IDs como HTML", async (method) => {
    await import("../../js/admin/shifts-calendar-dates.js")
    await import("../../js/admin/shifts-calendar-view.js")
    const state = { currentDate: new Date(2026, 9, 9) }
    const names = Array(7).fill("Día")
    const months = Array(12).fill("Mes")
    const dates = window.ShiftsCalendarDates.create({ state, VIEW_LABELS: {}, STATUS_LABELS: {}, DAY_NAMES: names, MONTH_NAMES: months })
    const view = window.ShiftsCalendarView.create({ state, DAY_NAMES: names, DAY_SHORT_NAMES: names, MONTH_NAMES: months, ...dates, normalizeTime: (value) => value, formatLongDate: () => payload })
    const container = document.createElement("div")
    view[method](container, [{ id: payload, date: "2026-10-09", caregiver_name: payload, older_adult_name: payload, start_time: "09:00", end_time: "17:00" }])
    expectInert(container)
    expect(container.querySelector("button").dataset.shiftId).toBe(payload)
    expect(container.querySelector("button").textContent).toContain(payload)
  })

  it("asigna sólo alturas CSS finitas y acotadas y etiquetas de texto", async () => {
    document.body.innerHTML = '<div id="usageChart"></div><div id="usageChartTitle"></div>'
    await import("../../js/admin/medication-stats-view.js")
    const view = window.MedicationStatsView.create({ state: {} })
    view.renderChart({ name: payload, chartTitle: "Adquisiciones", chart: [{ value: "1; background: url(javascript:alert(1))", label: payload }, { value: Infinity, label: "inf" }, { value: -1, label: "negative" }, { value: 5, label: "valid" }] })
    const chart = document.getElementById("usageChart")
    expectInert(chart)
    expect(chart.querySelector(".chart-bar-label").textContent).toBe(payload)
    expect([...chart.querySelectorAll(".chart-bar-fill")].map((node) => node.style.height)).toEqual(["4%", "4%", "4%", "100%"])
    expect(chart.querySelector('[style*="background"]')).toBeNull()
  })
})
