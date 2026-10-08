import { describe, expect, it, vi } from "vitest"

function todayKey() {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
}

describe("presentación de turnos en calendario", () => {
  it("renderiza en la vista semanal el cuidador, residente, horario y estado", async () => {
    document.body.innerHTML = `
      <button id="calendarPrevButton"></button><button id="calendarNextButton"></button><button id="calendarTodayButton"></button>
      <div id="calendarViewSwitcher"><button class="calendar-view-button" data-view="week"></button></div>
      <div id="calendarRangeText"></div><div id="shiftsCalendarContainer"></div><div id="calendarEventsList"></div><span id="calendarEventsCount"></span>
      <div id="shiftDetailModal" hidden></div><button id="closeShiftDetailModal"></button>
    `
    window.AuthSession = { getUser: vi.fn(() => ({ role: "admin" })) }
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn().mockResolvedValue({
        shifts: [{ id: 10, caregiver_name: "Pedro", older_adult_name: "Carlos", date: todayKey(), start_time: "09:30:00", end_time: "17:30:00", status: "completed" }],
        events: [],
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/admin/shifts-calendar-dates.js")
    await import("../../js/admin/shifts-calendar-view.js")
    await import("../../js/admin/shifts-calendar.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))
    await Promise.resolve()
    await Promise.resolve()
    await new Promise((resolve) => setTimeout(resolve, 0))

    const shift = document.querySelector(".week-shift-card")
    expect(shift.textContent).toContain("Pedro")
    expect(shift.textContent).toContain("Carlos")
    expect(shift.textContent).toContain("09:30 - 17:30")
    expect(shift.textContent).toContain("Completado")
    expect(shift.classList.contains("status-completed")).toBe(true)
  })
})
