(() => {
  function create({ state, VIEW_LABELS, STATUS_LABELS, DAY_NAMES, MONTH_NAMES }) {
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

    return Object.freeze({ startOfDay, addDays, startOfWeek, endOfWeek, startOfMonth, endOfMonth, isSameDay, formatDateKey, formatRangeText, getFetchRange, getStatusClass, statusLabel })
  }
  window.ShiftsCalendarDates = Object.freeze({ create })
})()
