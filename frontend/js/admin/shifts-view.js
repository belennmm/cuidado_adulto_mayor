(() => {
  function create({ state, caregiverSelect, shiftsTableBody, vacationsTableBody, DAY_LABELS, formatDate, formatTimeRange, statusLabel,
    normalizeTime = (value) => String(value || "").slice(0, 5),
    deleteSchedule = () => {}, resolveChangeRequest = () => {}, resolveVacationRequest = () => {},
  }) {
    const el = window.CuidadoUi.element
    const cell = (label, text, children = [], extraClass = "") => {
      const node = el("div", `shift-cell ${extraClass}`.trim(), text, children)
      node.dataset.label = label
      return node
    }
    const caregiverCell = (user) => cell("Cuidador", null, [
      el("div", "shift-avatar"), el("div", "shift-name-group", null, [el("span", "", user?.name || "Cuidador"), el("span", "shift-email", user?.email || "")]),
    ], "shift-caregiver")
    const button = (className, text, id, action) => {
      const node = el("button", className, text)
      node.type = "button"
      node.dataset.id = String(id ?? "")
      node.addEventListener("click", () => action(node.dataset.id))
      return node
    }
    function renderCaregiverOptions() {
      const option = (value, label) => {
        const node = el("option", "", label)
        node.value = String(value ?? "")
        return node
      }
      caregiverSelect.replaceChildren(option("", "Seleccionar cuidador"), ...state.caregivers.map((user) => option(user.id, `${user.name} (${user.email})`)))
      if (!state.caregivers.length) caregiverSelect.append(option("", "No hay cuidadores aprobados"))
    }
    function renderVacations() {
      if (!vacationsTableBody) return
      vacationsTableBody.replaceChildren()
      if (!state.vacations.length) {
        vacationsTableBody.append(el("div", "empty-state", "Todavía no hay solicitudes de vacaciones."))
        return
      }
      for (const request of state.vacations) {
        const status = ["pending", "approved", "rejected"].includes(request.status) ? request.status : "pending"
        const actions = request.status === "pending" ? [
          button("approve-vacation-button", "Aprobar", request.id, (id) => resolveVacationRequest(id, "approve")),
          button("reject-vacation-button", "Rechazar", request.id, (id) => resolveVacationRequest(id, "reject")),
        ] : [el("span", "request-empty", "Revisada")]
        vacationsTableBody.append(el("article", "vacation-admin-row", null, [
          caregiverCell(request.user), cell("Fechas", `${formatDate(request.start_date)} - ${formatDate(request.end_date)}`),
          cell("Motivo", request.reason || "Sin motivo"), cell("Estado", null, [el("span", `vacation-status vacation-status-${status}`, statusLabel(request.status))]),
          cell("Acción", null, actions),
        ]))
      }
    }
    function renderChangeRequest(schedule) {
      const request = schedule.change_request
      if (!request || request.status !== "pending") return el("span", "request-empty", "Sin solicitud")
      return el("div", "request-card", null, [
        el("strong", "", `${normalizeTime(request.start_time)} - ${normalizeTime(request.end_time)}`),
        el("span", "", request.notes || "Sin notas"), el("p", "", request.message || ""),
      ])
    }
    function renderSchedules() {
      shiftsTableBody.replaceChildren()
      if (!state.schedules.length) {
        shiftsTableBody.append(el("div", "empty-state", "Todavía no hay turnos asignados."))
        return
      }
      for (const schedule of state.schedules) {
        const actions = []
        if (schedule.change_request?.status === "pending") actions.push(
          button("approve-request-button", "Aprobar", schedule.id, (id) => resolveChangeRequest(id, "approve")),
          button("reject-request-button", "Rechazar", schedule.id, (id) => resolveChangeRequest(id, "reject")),
        )
        actions.push(button("delete-shift-button danger-soft-button", "Eliminar", schedule.id, deleteSchedule))
        shiftsTableBody.append(el("article", "shift-row", null, [
          caregiverCell(schedule.user), cell("Día", DAY_LABELS[schedule.day_of_week] || "Sin día"), cell("Horario", formatTimeRange(schedule)),
          cell("Notas", schedule.notes || "Sin notas"), cell("Solicitud", null, [renderChangeRequest(schedule)], "shift-request"), cell("Acción", null, actions),
        ]))
      }
    }
    return Object.freeze({ renderCaregiverOptions, renderVacations, renderSchedules, renderChangeRequest })
  }
  window.AdminShiftsView = Object.freeze({ create })
})()
