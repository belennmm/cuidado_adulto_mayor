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

  function createMockShifts() {
    const today = startOfDay(new Date())
    const weekStart = startOfWeek(today)

    return [
      {
        id: 1,
        caregiver: "Maria Gonzalez",
        olderAdult: "Rosa Martinez",
        date: formatDateKey(addDays(weekStart, 1)),
        startTime: "07:00",
        endTime: "13:00",
        status: "assigned",
        notes: "Acompanamiento matutino y apoyo con medicacion.",
      },
      {
        id: 2,
        caregiver: "Daniel Soto",
        olderAdult: "Carlos Ramirez",
        date: formatDateKey(addDays(weekStart, 2)),
        startTime: "09:00",
        endTime: "15:00",
        status: "pending",
        notes: "Pendiente confirmar cobertura por terapia fisica.",
      },
      {
        id: 3,
        caregiver: "Maria Gonzalez",
        olderAdult: "Miguel Herrera",
        date: formatDateKey(today),
        startTime: "08:00",
        endTime: "14:00",
        status: "completed",
        notes: "Turno completado con monitoreo de glicemia.",
      },
      {
        id: 4,
        caregiver: "Daniel Soto",
        olderAdult: "Elena Castillo",
        date: formatDateKey(addDays(today, 1)),
        startTime: "14:00",
        endTime: "20:00",
        status: "assigned",
        notes: "Supervision respiratoria en horario vespertino.",
      },
      {
        id: 5,
        caregiver: "Maria Gonzalez",
        olderAdult: "Rosa Martinez",
        date: formatDateKey(addDays(today, 3)),
        startTime: "07:00",
        endTime: "13:00",
        status: "cancelled",
        notes: "Turno cancelado por ajuste operativo.",
      },
      {
        id: 6,
        caregiver: "Daniel Soto",
        olderAdult: "Carlos Ramirez",
        date: formatDateKey(new Date(today.getFullYear(), today.getMonth(), 1)),
        startTime: "10:00",
        endTime: "16:00",
        status: "assigned",
        notes: "Cobertura de inicio de mes para actividades grupales.",
      },
      {
        id: 7,
        caregiver: "Maria Gonzalez",
        olderAdult: "Miguel Herrera",
        date: formatDateKey(addDays(endOfMonth(today), -2)),
        startTime: "12:00",
        endTime: "18:00",
        status: "pending",
        notes: "Pendiente reasignacion final de cierre de mes.",
      },
    ]
  }

  function getVisibleShifts() {
    const currentDate = state.currentDate

    if (state.view === VIEW_LABELS.day) {
      return state.shifts.filter((shift) => shift.date === formatDateKey(currentDate))
    }

    if (state.view === VIEW_LABELS.week) {
      const firstDay = startOfWeek(currentDate)
      const lastDay = endOfWeek(currentDate)

      return state.shifts.filter((shift) => {
        const shiftDate = new Date(`${shift.date}T00:00:00`)
        return shiftDate >= firstDay && shiftDate <= lastDay
      })
    }

    return state.shifts.filter((shift) => {
      const shiftDate = new Date(`${shift.date}T00:00:00`)
      return shiftDate.getMonth() === currentDate.getMonth() && shiftDate.getFullYear() === currentDate.getFullYear()
    })
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
                  <strong>${escapeHtml(shift.caregiver)}</strong>
                  <span>${escapeHtml(shift.olderAdult)}</span>
                  <span>${escapeHtml(`${normalizeTime(shift.startTime)} - ${normalizeTime(shift.endTime)}`)}</span>
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
                  <strong>${escapeHtml(shift.caregiver)}</strong>
                  <span>${escapeHtml(shift.olderAdult)}</span>
                  <span>${escapeHtml(`${normalizeTime(shift.startTime)} - ${normalizeTime(shift.endTime)}`)}</span>
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
      .sort((firstShift, secondShift) => firstShift.startTime.localeCompare(secondShift.startTime))

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
              <div class="day-shift-time">${escapeHtml(`${normalizeTime(shift.startTime)} - ${normalizeTime(shift.endTime)}`)}</div>
              <div class="day-shift-content">
                <strong>${escapeHtml(shift.caregiver)}</strong>
                <span>${escapeHtml(shift.olderAdult)}</span>
              </div>
              <span class="day-shift-status ${getStatusClass(shift.status)}">${escapeHtml(statusLabel(shift.status))}</span>
            </button>
          `).join("")}
        </div>
      </div>
    `
  }

  function openShiftDetail(shiftId) {
    const shift = state.shifts.find((item) => String(item.id) === String(shiftId))
    const modal = document.getElementById("shiftDetailModal")
    if (!shift || !modal) return

    document.getElementById("detailCaregiver").textContent = shift.caregiver
    document.getElementById("detailOlderAdult").textContent = shift.olderAdult
    document.getElementById("detailDate").textContent = formatLongDate(shift.date)
    document.getElementById("detailStartTime").textContent = normalizeTime(shift.startTime)
    document.getElementById("detailEndTime").textContent = normalizeTime(shift.endTime)
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

    const visibleShifts = getVisibleShifts()
    const rangeText = document.getElementById("calendarRangeText")
    if (rangeText) {
      rangeText.textContent = formatRangeText()
    }

    updateViewButtons()

    if (!visibleShifts.length) {
      renderEmptyState(container, "Prueba cambiando la fecha o la vista del calendario.")
      return
    }

    if (state.view === VIEW_LABELS.month) {
      renderMonthView(container, visibleShifts)
      return
    }

    if (state.view === VIEW_LABELS.week) {
      renderWeekView(container, visibleShifts)
      return
    }

    renderDayView(container, visibleShifts)
  }

  function moveCalendar(direction) {
    if (state.view === VIEW_LABELS.day) {
      state.currentDate = addDays(state.currentDate, direction)
    } else if (state.view === VIEW_LABELS.week) {
      state.currentDate = addDays(state.currentDate, direction * 7)
    } else {
      state.currentDate = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() + direction, 1)
    }

    renderCalendar()
  }

  function setToday() {
    state.currentDate = startOfDay(new Date())
    renderCalendar()
  }

  function setView(view) {
    state.view = view
    renderCalendar()
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
    state.shifts = createMockShifts()

    document.getElementById("calendarPrevButton")?.addEventListener("click", () => moveCalendar(-1))
    document.getElementById("calendarNextButton")?.addEventListener("click", () => moveCalendar(1))
    document.getElementById("calendarTodayButton")?.addEventListener("click", setToday)
    document.getElementById("calendarViewSwitcher")?.addEventListener("click", (event) => {
      const button = event.target.closest(".calendar-view-button[data-view]")
      if (!button) return
      setView(button.dataset.view)
    })

    document.getElementById("shiftsCalendarContainer")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-shift-id]")
      if (!button) return
      openShiftDetail(button.dataset.shiftId)
    })

    document.getElementById("closeShiftDetailModal")?.addEventListener("click", closeShiftDetail)
    document.getElementById("shiftDetailModal")?.addEventListener("click", (event) => {
      if (event.target.id === "shiftDetailModal") {
        closeShiftDetail()
      }
    })

    renderCalendar()
  })
})()
