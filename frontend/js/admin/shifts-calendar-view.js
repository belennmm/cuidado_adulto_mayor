(() => {
  function create({ state, DAY_NAMES, DAY_SHORT_NAMES, MONTH_NAMES, escapeHtml, startOfDay, addDays, startOfWeek, startOfMonth, isSameDay, formatDateKey, getStatusClass, statusLabel, normalizeTime, formatLongDate }) {
    const el = window.CuidadoUi.element
    function renderEmptyState(container, message) {
      container.replaceChildren(el("div", "calendar-empty-state", null, [el("i", "bx bx-calendar-x"), el("strong", "", "No hay turnos para esta vista."), el("span", "", message)]))
    }
    function renderLoadingState(container) {
      container.replaceChildren(el("div", "calendar-loading-state", null, [el("div", "calendar-loading-spinner"), el("strong", "", "Cargando turnos..."), el("span", "", "Estamos preparando el calendario para la vista seleccionada.")]))
    }
    function renderErrorState(container, message) {
      container.replaceChildren(el("div", "calendar-error-state", null, [el("i", "bx bx-error-circle"), el("strong", "", "No se pudo cargar el calendario."), el("span", "", message)]))
    }
    function shiftButton(shift, className, day = false, status = false) {
      const range = `${normalizeTime(shift.start_time)} - ${normalizeTime(shift.end_time)}`
      const content = day ? [
        el("div", "day-shift-time", range), el("div", "day-shift-content", null, [el("strong", "", shift.caregiver_name), el("span", "", shift.older_adult_name)]),
        el("span", `day-shift-status ${getStatusClass(shift.status)}`, statusLabel(shift.status)),
      ] : [el("strong", "", shift.caregiver_name), el("span", "", shift.older_adult_name), el("span", "", range), status ? el("span", "", statusLabel(shift.status)) : null]
      const button = el("button", `${className} ${getStatusClass(shift.status)}`, null, content)
      button.type = "button"
      button.dataset.shiftId = String(shift.id ?? "")
      return button
    }
    function renderMonthView(container, shifts) {
      const monthStart = startOfMonth(state.currentDate)
      const calendarStart = addDays(monthStart, -monthStart.getDay())
      const today = startOfDay(new Date())
      const days = []
      for (let index = 0; index < 42; index += 1) {
        const day = addDays(calendarStart, index)
        const dayShifts = shifts.filter((shift) => shift.date === formatDateKey(day))
        const cell = el("div", "month-day-cell", null, [el("span", "month-day-number", day.getDate()), el("div", "month-day-shifts", null, dayShifts.map((shift) => shiftButton(shift, "calendar-shift-chip")))])
        cell.classList.toggle("is-outside-month", day.getMonth() !== state.currentDate.getMonth())
        cell.classList.toggle("is-today", isSameDay(day, today))
        days.push(cell)
      }
      container.replaceChildren(el("div", "month-calendar", null, [DAY_SHORT_NAMES.map((name) => el("div", "month-day-head", name)), days]))
    }
    function renderWeekView(container, shifts) {
      const firstDay = startOfWeek(state.currentDate)
      const today = startOfDay(new Date())
      const columns = []
      for (let index = 0; index < 7; index += 1) {
        const day = addDays(firstDay, index)
        const dayShifts = shifts.filter((shift) => shift.date === formatDateKey(day))
        const column = el("div", "week-day-column", null, [
          el("div", "week-day-title", null, [el("strong", "", DAY_NAMES[day.getDay()]), el("span", "", `${day.getDate()} de ${MONTH_NAMES[day.getMonth()]}`)]),
          el("div", "week-day-shifts", null, dayShifts.length ? dayShifts.map((shift) => shiftButton(shift, "week-shift-card", false, true)) : [el("div", "request-empty", "Sin turnos")]),
        ])
        column.classList.toggle("is-today", isSameDay(day, today))
        columns.push(column)
      }
      container.replaceChildren(el("div", "week-calendar", null, columns))
    }
    function renderDayView(container, shifts) {
      const key = formatDateKey(state.currentDate)
      const dayShifts = shifts.filter((shift) => shift.date === key).sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)))
      if (!dayShifts.length) return renderEmptyState(container, "No hay turnos programados para la fecha seleccionada.")
      container.replaceChildren(el("div", "day-calendar", null, [
        el("div", "day-summary-card", null, [el("h2", "", formatLongDate(key)), el("p", "", `${dayShifts.length} turno(s) programado(s) para esta fecha.`)]),
        el("div", "day-shifts-list", null, dayShifts.map((shift) => shiftButton(shift, "day-shift-card", true))),
      ]))
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

