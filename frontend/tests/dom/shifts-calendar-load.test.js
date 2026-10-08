import { describe, expect, it, vi } from "vitest"

function todayKey() {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
}

function renderCalendar() {
  document.body.innerHTML = `
    <button id="calendarPrevButton"></button><button id="calendarNextButton"></button><button id="calendarTodayButton"></button>
    <div id="calendarViewSwitcher"><button class="calendar-view-button" data-view="week"></button></div>
    <div id="calendarRangeText"></div><div id="shiftsCalendarContainer"></div><div id="calendarEventsList"></div><span id="calendarEventsCount"></span>
    <div id="shiftDetailModal" hidden></div><button id="closeShiftDetailModal"></button>
  `
}

describe("carga del calendario de turnos", () => {
  it("consulta y carga los turnos simulados para el rango actual", async () => {
    renderCalendar()
    const date = todayKey()
    window.AuthSession = { getUser: vi.fn(() => ({ id: 1, role: "admin" })) }
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn().mockResolvedValue({
        shifts: [{ id: 4, caregiver_name: "Marta", older_adult_name: "Rosa", date, start_time: "08:00:00", end_time: "16:00:00", status: "assigned" }],
        events: [{ id: 2, type: "incident", title: "Revisión", person: "Rosa", date, status: "pending" }],
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

    expect(window.CuidadoApi.getToken).toHaveBeenCalledWith(["admin"])
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith(expect.stringMatching(/^\/admin\/schedules\/calendar\?/), expect.objectContaining({ expectedRoles: ["admin"] }))
    expect(document.getElementById("shiftsCalendarContainer").textContent).toContain("Marta")
    expect(document.getElementById("calendarEventsCount").textContent).toBe("1 evento")
  })
})
