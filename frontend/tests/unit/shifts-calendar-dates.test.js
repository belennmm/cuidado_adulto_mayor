import { beforeEach, describe, expect, it } from "vitest"

const VIEW_LABELS = { day: "day", week: "week", month: "month" }
const STATUS_LABELS = { assigned: "Asignado", pending: "Pendiente", completed: "Completado", cancelled: "Cancelado" }
const DAY_NAMES = ["Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado"]
const MONTH_NAMES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"]

describe("ShiftsCalendarDates", () => {
  let state
  let dates

  beforeEach(async () => {
    await import("../../js/admin/shifts-calendar-dates.js")
    state = { currentDate: new Date(2026, 8, 2), view: VIEW_LABELS.week }
    dates = window.ShiftsCalendarDates.create({ state, VIEW_LABELS, STATUS_LABELS, DAY_NAMES, MONTH_NAMES })
  })

  it("calcula el rango semanal de domingo a sábado", () => {
    const range = dates.getFetchRange()
    expect(dates.formatDateKey(range.startDate)).toBe("2026-08-30")
    expect(dates.formatDateKey(range.endDate)).toBe("2026-09-05")
    expect(dates.formatRangeText()).toBe("30 de agosto - 5 de septiembre de 2026")
  })

  it("calcula las 42 celdas de la vista mensual", () => {
    state.view = VIEW_LABELS.month
    const range = dates.getFetchRange()
    expect(dates.formatDateKey(range.startDate)).toBe("2026-08-30")
    expect(dates.formatDateKey(range.endDate)).toBe("2026-10-10")
    expect(dates.formatRangeText()).toBe("septiembre de 2026")
  })

  it("normaliza etiquetas y clases de estado", () => {
    expect(dates.getStatusClass("completed")).toBe("status-completed")
    expect(dates.getStatusClass("unknown")).toBe("status-assigned")
    expect(dates.statusLabel("pending")).toBe("Pendiente")
    expect(dates.statusLabel("unknown")).toBe("Asignado")
  })
})
