(() => {
  function create({ state, DAY_NAMES, DAY_SHORT_NAMES, MONTH_NAMES, escapeHtml, startOfDay, addDays, startOfWeek, startOfMonth, isSameDay, formatDateKey, getStatusClass, statusLabel, normalizeTime, formatLongDate }) {
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

    return Object.freeze({ renderEmptyState, renderLoadingState, renderErrorState, renderMonthView, renderWeekView, renderDayView, eventTypeLabel, eventStatusLabel, renderEvents })
  }
  window.ShiftsCalendarView = Object.freeze({ create })
})()

