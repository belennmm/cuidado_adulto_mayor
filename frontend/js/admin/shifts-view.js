(() => {
  function create({ state, caregiverSelect, shiftsTableBody, vacationsTableBody, DAY_LABELS, escapeHtml, formatDate, formatTimeRange, statusLabel }) {
    function renderCaregiverOptions() {
      caregiverSelect.innerHTML = `<option value="">Seleccionar cuidador</option>`
    
      state.caregivers.forEach((caregiver) => {
        const option = document.createElement("option")
        option.value = caregiver.id
        option.textContent = `${caregiver.name} (${caregiver.email})`
        caregiverSelect.appendChild(option)
      })
    
      if (!state.caregivers.length) {
        const option = document.createElement("option")
        option.value = ""
        option.textContent = "No hay cuidadores aprobados"
        caregiverSelect.appendChild(option)
      }
    }
    
    function renderVacations() {
      if (!vacationsTableBody) return
      vacationsTableBody.innerHTML = ""
    
      if (!state.vacations.length) {
        vacationsTableBody.innerHTML = `
          <div class="empty-state">
            Todavía no hay solicitudes de vacaciones.
          </div>
        `
        return
      }
    
      state.vacations.forEach((request) => {
        const row = document.createElement("article")
        row.className = "vacation-admin-row"
    
        row.innerHTML = `
          <div class="shift-cell shift-caregiver" data-label="Cuidador">
            <div class="shift-avatar"></div>
            <div class="shift-name-group">
              <span>${escapeHtml(request.user?.name || "Cuidador")}</span>
              <span class="shift-email">${escapeHtml(request.user?.email || "")}</span>
            </div>
          </div>
    
          <div class="shift-cell" data-label="Fechas">
            ${escapeHtml(formatDate(request.start_date))} - ${escapeHtml(formatDate(request.end_date))}
          </div>
    
          <div class="shift-cell" data-label="Motivo">
            ${escapeHtml(request.reason || "Sin motivo")}
          </div>
    
          <div class="shift-cell" data-label="Estado">
            <span class="vacation-status vacation-status-${escapeHtml(request.status)}">
              ${escapeHtml(statusLabel(request.status))}
            </span>
          </div>
    
          <div class="shift-cell" data-label="Acción">
            ${request.status === "pending" ? `
              <button type="button" class="approve-vacation-button" data-id="${request.id}">
                Aprobar
              </button>
              <button type="button" class="reject-vacation-button" data-id="${request.id}">
                Rechazar
              </button>
            ` : `<span class="request-empty">Revisada</span>`}
          </div>
        `
    
        vacationsTableBody.appendChild(row)
      })
    
      document.querySelectorAll(".approve-vacation-button").forEach((button) => {
        button.addEventListener("click", () => resolveVacationRequest(button.dataset.id, "approve"))
      })
    
      document.querySelectorAll(".reject-vacation-button").forEach((button) => {
        button.addEventListener("click", () => resolveVacationRequest(button.dataset.id, "reject"))
      })
    }
    
    function renderSchedules() {
      shiftsTableBody.innerHTML = ""
    
      if (!state.schedules.length) {
        shiftsTableBody.innerHTML = `
          <div class="empty-state">
            Todavía no hay turnos asignados.
          </div>
        `
        return
      }
    
      state.schedules.forEach((schedule) => {
        const row = document.createElement("article")
        row.className = "shift-row"
    
        row.innerHTML = `
          <div class="shift-cell shift-caregiver" data-label="Cuidador">
            <div class="shift-avatar"></div>
            <div class="shift-name-group">
              <span>${escapeHtml(schedule.user?.name || "Cuidador")}</span>
              <span class="shift-email">${escapeHtml(schedule.user?.email || "")}</span>
            </div>
          </div>
    
          <div class="shift-cell" data-label="Día">
            ${escapeHtml(DAY_LABELS[schedule.day_of_week] || "Sin día")}
          </div>
    
          <div class="shift-cell" data-label="Horario">
            ${escapeHtml(formatTimeRange(schedule))}
          </div>
    
          <div class="shift-cell" data-label="Notas">
            ${escapeHtml(schedule.notes || "Sin notas")}
          </div>
    
          <div class="shift-cell shift-request" data-label="Solicitud">
            ${renderChangeRequest(schedule)}
          </div>
    
          <div class="shift-cell" data-label="Acción">
            ${schedule.change_request?.status === "pending" ? `
              <button type="button" class="approve-request-button" data-id="${schedule.id}">
                Aprobar
              </button>
              <button type="button" class="reject-request-button" data-id="${schedule.id}">
                Rechazar
              </button>
            ` : ""}
            <button type="button" class="delete-shift-button danger-soft-button" data-id="${schedule.id}">
              Eliminar
            </button>
          </div>
        `
    
        shiftsTableBody.appendChild(row)
      })
    
      document.querySelectorAll(".delete-shift-button").forEach((button) => {
        button.addEventListener("click", () => deleteSchedule(button.dataset.id))
      })
    
      document.querySelectorAll(".approve-request-button").forEach((button) => {
        button.addEventListener("click", () => resolveChangeRequest(button.dataset.id, "approve"))
      })
    
      document.querySelectorAll(".reject-request-button").forEach((button) => {
        button.addEventListener("click", () => resolveChangeRequest(button.dataset.id, "reject"))
      })
    }
    
    function renderChangeRequest(schedule) {
      const request = schedule.change_request
    
      if (!request || request.status !== "pending") {
        return `<span class="request-empty">Sin solicitud</span>`
      }
    
      return `
        <div class="request-card">
          <strong>${escapeHtml(normalizeTime(request.start_time))} - ${escapeHtml(normalizeTime(request.end_time))}</strong>
          <span>${escapeHtml(request.notes || "Sin notas")}</span>
          <p>${escapeHtml(request.message || "")}</p>
        </div>
      `
    }
    
        return Object.freeze({ renderCaregiverOptions, renderVacations, renderSchedules, renderChangeRequest })
  }
  window.AdminShiftsView = Object.freeze({ create })
})()

