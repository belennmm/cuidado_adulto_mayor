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

  const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

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

  function getToken() {
    return localStorage.getItem("token")
  }

  function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate())
  }

  function addDays(date, amount) {
    const next = new Date(date)
    next.setDate(next.getDate() + amount)
    return next
  }

  function startOfWeek(date) {
    const day = date.getDay()
    return startOfDay(addDays(date, -day))
  }

  function endOfWeek(date) {
    return startOfDay(addDays(startOfWeek(date), 6))
  }

  function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1)
  }

  function endOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0)
  }

  function isSameDay(firstDate, secondDate) {
    return startOfDay(firstDate).getTime() === startOfDay(secondDate).getTime()
  }

  function formatDateKey(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, "0")
    const day = String(date.getDate()).padStart(2, "0")
    return `${year}-${month}-${day}`
  }

  function formatRangeText() {
    const currentDate = state.currentDate

    if (state.view === VIEW_LABELS.day) {
      return `${DAY_NAMES[currentDate.getDay()]}, ${currentDate.getDate()} de ${MONTH_NAMES[currentDate.getMonth()]} de ${currentDate.getFullYear()}`
    }

    if (state.view === VIEW_LABELS.week) {
      const firstDay = startOfWeek(currentDate)
      const lastDay = endOfWeek(currentDate)
      const firstMonth = MONTH_NAMES[firstDay.getMonth()]
      const lastMonth = MONTH_NAMES[lastDay.getMonth()]

      if (firstDay.getMonth() === lastDay.getMonth()) {
        return `${firstDay.getDate()} - ${lastDay.getDate()} de ${firstMonth} de ${lastDay.getFullYear()}`
      }

      return `${firstDay.getDate()} de ${firstMonth} - ${lastDay.getDate()} de ${lastMonth} de ${lastDay.getFullYear()}`
    }

    return `${MONTH_NAMES[currentDate.getMonth()]} de ${currentDate.getFullYear()}`
  }

  function getFetchRange() {
    if (state.view === VIEW_LABELS.day) {
      return {
        startDate: startOfDay(state.currentDate),
        endDate: startOfDay(state.currentDate),
      }
    }

    if (state.view === VIEW_LABELS.week) {
      return {
        startDate: startOfWeek(state.currentDate),
        endDate: endOfWeek(state.currentDate),
      }
    }

    const monthStart = startOfMonth(state.currentDate)
    const calendarStart = addDays(monthStart, -monthStart.getDay())

    return {
      startDate: calendarStart,
      endDate: addDays(calendarStart, 41),
    }
  }

  function getStatusClass(status) {
    if (status === "completed") return "status-completed"
    if (status === "pending") return "status-pending"
    if (status === "cancelled") return "status-cancelled"
    return "status-assigned"
  }

  function statusLabel(status) {
    return STATUS_LABELS[status] || "Asignado"
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;")
  }

  function normalizeTime(value) {
    return String(value || "").slice(0, 5)
  }

  function formatLongDate(dateString) {
    const date = new Date(`${dateString}T00:00:00`)
    return `${DAY_NAMES[date.getDay()]}, ${date.getDate()} de ${MONTH_NAMES[date.getMonth()]} de ${date.getFullYear()}`
  }

  async function fetchCalendarShifts() {
    const token = getToken()

    if (!token) {
      throw new Error("Inicia sesion como administrador para ver el calendario.")
    }

    const { startDate, endDate } = getFetchRange()
    const params = new URLSearchParams({
      start_date: formatDateKey(startDate),
      end_date: formatDateKey(endDate),
    })

    const response = await fetch(`${API_URL}/admin/schedules/calendar?${params.toString()}`, {
      cache: "no-store",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
    })

    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(data.message || "No se pudo cargar el calendario de turnos.")
    }

    return {
      shifts: data.shifts || [],
      events: data.events || [],
    }
  }

  function renderEmptyState(container, message) {
    container.innerHTML = `
      <div class="calendar-empty-state">
        <i class="bx bx-calendar-x"></i>
        <strong>No hay turnos para esta vista.</strong>
        <span>${escapeHtml(message)}</span>
      </div>
    `
  }

  function renderLoadingState(container) {
    container.innerHTML = `
      <div class="calendar-loading-state">
        <div class="calendar-loading-spinner"></div>
        <strong>Cargando turnos...</strong>
        <span>Estamos preparando el calendario para la vista seleccionada.</span>
      </div>
    `
  }

  function renderErrorState(container, message) {
    container.innerHTML = `
      <div class="calendar-error-state">
        <i class="bx bx-error-circle"></i>
        <strong>No se pudo cargar el calendario.</strong>
        <span>${escapeHtml(message)}</span>
      </div>
    `
  }

  function renderMonthView(container, shifts) {
    const monthStart = startOfMonth(state.currentDate)
    const calendarStart = addDays(monthStart, -monthStart.getDay())
    const today = startOfDay(new Date())
    const days = []

    for (let index = 0; index < 42; index += 1) {
      const day = addDays(calendarStart, index)
      const dayKey = formatDateKey(day)
      const dayShifts = shifts.filter((shift) => shift.date === dayKey)
      days.push(`
        <div class="month-day-cell ${day.getMonth() !== state.currentDate.getMonth() ? "is-outside-month" : ""} ${isSameDay(day, today) ? "is-today" : ""}">
          <span class="month-day-number">${day.getDate()}</span>
          <div class="month-day-shifts">
            ${dayShifts.length
              ? dayShifts.map((shift) => `
                <button type="button" class="calendar-shift-chip ${getStatusClass(shift.status)}" data-shift-id="${shift.id}">
                  <strong>${escapeHtml(shift.caregiver_name)}</strong>
                  <span>${escapeHtml(shift.older_adult_name)}</span>
                  <span>${escapeHtml(`${normalizeTime(shift.start_time)} - ${normalizeTime(shift.end_time)}`)}</span>
                </button>
              `).join("")
              : ""
            }
          </div>
        </div>
      `)
    }

    container.innerHTML = `
      <div class="month-calendar">
        ${DAY_SHORT_NAMES.map((dayName) => `<div class="month-day-head">${dayName}</div>`).join("")}
        ${days.join("")}
      </div>
    `
  }

  function renderWeekView(container, shifts) {
    const firstDay = startOfWeek(state.currentDate)
    const today = startOfDay(new Date())
    const columns = []

    for (let index = 0; index < 7; index += 1) {
      const day = addDays(firstDay, index)
      const dayKey = formatDateKey(day)
      const dayShifts = shifts.filter((shift) => shift.date === dayKey)

      columns.push(`
        <div class="week-day-column ${isSameDay(day, today) ? "is-today" : ""}">
          <div class="week-day-title">
            <strong>${DAY_NAMES[day.getDay()]}</strong>
            <span>${day.getDate()} de ${MONTH_NAMES[day.getMonth()]}</span>
          </div>
          <div class="week-day-shifts">
            ${dayShifts.length
              ? dayShifts.map((shift) => `
                <button type="button" class="week-shift-card ${getStatusClass(shift.status)}" data-shift-id="${shift.id}">
                  <strong>${escapeHtml(shift.caregiver_name)}</strong>
                  <span>${escapeHtml(shift.older_adult_name)}</span>
                  <span>${escapeHtml(`${normalizeTime(shift.start_time)} - ${normalizeTime(shift.end_time)}`)}</span>
                  <span>${escapeHtml(statusLabel(shift.status))}</span>
                </button>
              `).join("")
              : `<div class="request-empty">Sin turnos</div>`
            }
          </div>
        </div>
      `)
    }

    container.innerHTML = `<div class="week-calendar">${columns.join("")}</div>`
  }

  function renderDayView(container, shifts) {
    const currentDateKey = formatDateKey(state.currentDate)
    const dayShifts = shifts
      .filter((shift) => shift.date === currentDateKey)
      .sort((firstShift, secondShift) => firstShift.start_time.localeCompare(secondShift.start_time))

    if (!dayShifts.length) {
      renderEmptyState(container, "No hay turnos programados para la fecha seleccionada.")
      return
    }

    container.innerHTML = `
      <div class="day-calendar">
        <div class="day-summary-card">
          <h2>${formatLongDate(currentDateKey)}</h2>
          <p>${dayShifts.length} turno(s) programado(s) para esta fecha.</p>
        </div>
        <div class="day-shifts-list">
          ${dayShifts.map((shift) => `
            <button type="button" class="day-shift-card ${getStatusClass(shift.status)}" data-shift-id="${shift.id}">
              <div class="day-shift-time">${escapeHtml(`${normalizeTime(shift.start_time)} - ${normalizeTime(shift.end_time)}`)}</div>
              <div class="day-shift-content">
                <strong>${escapeHtml(shift.caregiver_name)}</strong>
                <span>${escapeHtml(shift.older_adult_name)}</span>
              </div>
              <span class="day-shift-status ${getStatusClass(shift.status)}">${escapeHtml(statusLabel(shift.status))}</span>
            </button>
          `).join("")}
        </div>
      </div>
    `
  }

  function eventTypeLabel(type) {
    if (type === "vacation") return "Vacaciones"
    if (type === "incident") return "Incidente"
    return "Turno"
  }

  function eventStatusLabel(status) {
    if (status === "approved") return "Aprobado"
    if (status === "rejected") return "Rechazado"
    if (status === "pending") return "Pendiente"
    if (status === "completed") return "Completado"
    if (status === "cancelled") return "Cancelado"
    if (status === "resolved" || status === "resuelto") return "Resuelto"
    return status || "Activo"
  }

  function renderEvents() {
    const list = document.getElementById("calendarEventsList")
    const count = document.getElementById("calendarEventsCount")
    if (!list) return

    if (count) {
      count.textContent = `${state.events.length} evento${state.events.length === 1 ? "" : "s"}`
    }

    if (!state.events.length) {
      list.innerHTML = `
        <div class="calendar-events-empty">
          No hay eventos para el rango seleccionado.
        </div>
      `
      return
    }

    list.innerHTML = state.events
      .map((event) => `
        <article class="calendar-event-item event-${escapeHtml(event.type)}" data-event-id="${escapeHtml(event.id)}">
          <div class="calendar-event-icon">
            <i class="bx ${event.type === "incident" ? "bxs-error" : event.type === "vacation" ? "bxs-calendar-x" : "bxs-time"}"></i>
          </div>
          <div class="calendar-event-content">
            <div class="calendar-event-top">
              <strong>${escapeHtml(event.title)}</strong>
              <span>${escapeHtml(eventTypeLabel(event.type))}</span>
            </div>
            <p>${escapeHtml(event.person || "Sin persona asociada")}</p>
            <small>${escapeHtml(formatLongDate(event.date))}${event.time ? ` - ${escapeHtml(normalizeTime(event.time))}` : ""}</small>
          </div>
          <span class="calendar-event-status">${escapeHtml(eventStatusLabel(event.status))}</span>
        </article>
      `)
      .join("")
  }

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

    alert(message)
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
    const token = localStorage.getItem("token")
    const user = safeJsonParse(localStorage.getItem("user"))
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
