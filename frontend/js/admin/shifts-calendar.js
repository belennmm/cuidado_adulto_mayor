(() => {
  const VIEW_LABELS = {
    day: "day",
    week: "week",
    month: "month",
  }

  const STATUS_LABELS = {
    assigned: "Asignado",
    pending: "Pendiente",
    completed: "Completado",
    cancelled: "Cancelado",
  }

  const DAY_NAMES = ["Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado"]
  const DAY_SHORT_NAMES = ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"]
  const MONTH_NAMES = [
    "enero",
    "febrero",
    "marzo",
    "abril",
    "mayo",
    "junio",
    "julio",
    "agosto",
    "septiembre",
    "octubre",
    "noviembre",
    "diciembre",
  ]

  const state = {
    currentDate: new Date(),
    view: VIEW_LABELS.week,
    shifts: [],
    events: [],
    requestId: 0,
  }

  function safeJsonParse(value) {
    try {
      return JSON.parse(value)
    } catch {
      return null
    }
  }

  function navigateToLogin() {
    if (window.navigateWithLoading) {
      window.navigateWithLoading("../../index.html")
      return
    }

    window.location.assign("../../index.html")
  }

  const { startOfDay, addDays, startOfWeek, endOfWeek, startOfMonth, endOfMonth, isSameDay, formatDateKey, formatRangeText, getFetchRange, getStatusClass, statusLabel } = window.ShiftsCalendarDates.create({ state, VIEW_LABELS, STATUS_LABELS, DAY_NAMES, MONTH_NAMES })

  const escapeHtml = window.CuidadoUi.escapeHtml

  function normalizeTime(value) {
    return String(value || "").slice(0, 5)
  }

  function formatLongDate(dateString) {
    const date = new Date(`${dateString}T00:00:00`)
    return `${DAY_NAMES[date.getDay()]}, ${date.getDate()} de ${MONTH_NAMES[date.getMonth()]} de ${date.getFullYear()}`
  }

  async function fetchCalendarShifts() {
    if (!window.CuidadoApi.getToken(["admin"])) {
      throw new Error("Inicia sesión como administrador para ver el calendario.")
    }

    const { startDate, endDate } = getFetchRange()
    const params = new URLSearchParams({
      start_date: formatDateKey(startDate),
      end_date: formatDateKey(endDate),
    })

    const data = await window.CuidadoApi.fetchJson(`/admin/schedules/calendar?${params.toString()}`, {
      expectedRoles: ["admin"],
      fallbackError: "No se pudo cargar el calendario de turnos.",
    })

    return {
      shifts: data.shifts || [],
      events: data.events || [],
    }
  }

  const { renderEmptyState, renderLoadingState, renderErrorState, renderMonthView, renderWeekView, renderDayView, eventTypeLabel, eventStatusLabel, renderEvents } = window.ShiftsCalendarView.create({ state, DAY_NAMES, DAY_SHORT_NAMES, MONTH_NAMES, escapeHtml, startOfDay, addDays, startOfWeek, startOfMonth, isSameDay, formatDateKey, getStatusClass, statusLabel, normalizeTime, formatLongDate })

  async function openEventDetail(eventId) {
    const event = state.events.find((item) => String(item.id) === String(eventId))
    if (!event) return

    const message = [
      `Tipo: ${eventTypeLabel(event.type)}`,
      `Fecha: ${formatLongDate(event.date)}`,
      event.time ? `Hora: ${normalizeTime(event.time)}` : "",
      event.person ? `Persona: ${event.person}` : "",
      `Estado: ${eventStatusLabel(event.status)}`,
      "",
      event.description || "Sin descripcion.",
    ].filter((line) => line !== "").join("\n")

    if (window.showAdminAlert) {
      await window.showAdminAlert(message, { title: event.title })
      return
    }

    console.warn(message)
  }

  function openShiftDetail(shiftId) {
    const shift = state.shifts.find((item) => String(item.id) === String(shiftId))
    const modal = document.getElementById("shiftDetailModal")
    if (!shift || !modal) return

    document.getElementById("detailCaregiver").textContent = shift.caregiver_name
    document.getElementById("detailOlderAdult").textContent = shift.older_adult_name
    document.getElementById("detailDate").textContent = formatLongDate(shift.date)
    document.getElementById("detailStartTime").textContent = normalizeTime(shift.start_time)
    document.getElementById("detailEndTime").textContent = normalizeTime(shift.end_time)
    document.getElementById("detailStatus").textContent = statusLabel(shift.status)
    document.getElementById("detailStatus").className = getStatusClass(shift.status)
    document.getElementById("detailNotes").textContent = shift.notes || "Sin notas registradas."
    modal.hidden = false
  }

  function closeShiftDetail() {
    const modal = document.getElementById("shiftDetailModal")
    if (modal) {
      modal.hidden = true
    }
  }

  function updateViewButtons() {
    document.querySelectorAll(".calendar-view-button").forEach((button) => {
      button.classList.toggle("active", button.dataset.view === state.view)
    })
  }

  function renderCalendar() {
    const container = document.getElementById("shiftsCalendarContainer")
    if (!container) return

    const rangeText = document.getElementById("calendarRangeText")
    if (rangeText) {
      rangeText.textContent = formatRangeText()
    }

    updateViewButtons()

    if (!state.shifts.length) {
      renderEmptyState(container, "Prueba cambiando la fecha o la vista del calendario.")
      renderEvents()
      return
    }

    if (state.view === VIEW_LABELS.month) {
      renderMonthView(container, state.shifts)
      renderEvents()
      return
    }

    if (state.view === VIEW_LABELS.week) {
      renderWeekView(container, state.shifts)
      renderEvents()
      return
    }

    renderDayView(container, state.shifts)
    renderEvents()
  }

  async function refreshCalendar() {
    const container = document.getElementById("shiftsCalendarContainer")
    if (!container) return

    closeShiftDetail()
    const currentRequestId = state.requestId + 1
    state.requestId = currentRequestId
    renderLoadingState(container)

    try {
      const data = await fetchCalendarShifts()
      if (currentRequestId !== state.requestId) return
      state.shifts = data.shifts
      state.events = data.events
      renderCalendar()
    } catch (error) {
      if (currentRequestId !== state.requestId) return
      state.shifts = []
      state.events = []
      renderEvents()
      renderErrorState(container, error.message)
    }
  }

  async function moveCalendar(direction) {
    if (state.view === VIEW_LABELS.day) {
      state.currentDate = addDays(state.currentDate, direction)
    } else if (state.view === VIEW_LABELS.week) {
      state.currentDate = addDays(state.currentDate, direction * 7)
    } else {
      state.currentDate = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() + direction, 1)
    }

    await refreshCalendar()
  }

  async function setToday() {
    state.currentDate = startOfDay(new Date())
    await refreshCalendar()
  }

  async function setView(view) {
    state.view = view
    await refreshCalendar()
  }

  document.addEventListener("DOMContentLoaded", () => {
    const token = window.CuidadoApi.getToken(["admin"])
    const user = safeJsonParse(JSON.stringify(window.AuthSession?.getUser() || null))
    const role = String(user?.role || "").trim().toLowerCase()

    if (!token || role !== "admin") {
      navigateToLogin()
      return
    }

    state.currentDate = startOfDay(new Date())
    state.shifts = []

    document.getElementById("calendarPrevButton")?.addEventListener("click", async () => {
      await moveCalendar(-1)
    })
    document.getElementById("calendarNextButton")?.addEventListener("click", async () => {
      await moveCalendar(1)
    })
    document.getElementById("calendarTodayButton")?.addEventListener("click", async () => {
      await setToday()
    })
    document.getElementById("calendarViewSwitcher")?.addEventListener("click", async (event) => {
      const button = event.target.closest(".calendar-view-button[data-view]")
      if (!button) return
      await setView(button.dataset.view)
    })

    document.getElementById("shiftsCalendarContainer")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-shift-id]")
      if (!button) return
      openShiftDetail(button.dataset.shiftId)
    })

    document.getElementById("calendarEventsList")?.addEventListener("click", (event) => {
      const item = event.target.closest("[data-event-id]")
      if (!item) return
      openEventDetail(item.dataset.eventId)
    })

    document.getElementById("closeShiftDetailModal")?.addEventListener("click", closeShiftDetail)
    document.getElementById("shiftDetailModal")?.addEventListener("click", (event) => {
      if (event.target.id === "shiftDetailModal") {
        closeShiftDetail()
      }
    })

    refreshCalendar()
  })
})()
