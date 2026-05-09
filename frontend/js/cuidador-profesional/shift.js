(() => {
  const api = window.ProfessionalCare
  const days = {
    0: "Domingo",
    1: "Lunes",
    2: "Martes",
    3: "Miercoles",
    4: "Jueves",
    5: "Viernes",
    6: "Sabado",
  }

  function renderSchedule(schedule) {
    const pendingRequest = schedule.change_request?.status === "pending"
    const requestSummary = pendingRequest
      ? `
        <div class="schedule-request-summary">
          <strong>Solicitud pendiente</strong>
          <span>${api.escapeHtml(api.formatTime(schedule.change_request.start_time))} - ${api.escapeHtml(api.formatTime(schedule.change_request.end_time))}</span>
          <p>${api.escapeHtml(schedule.change_request.message || "")}</p>
        </div>
      `
      : ""

    return `
      <article class="professional-row schedule-row">
        <span class="row-icon"><i class="bx bxs-time"></i></span>
        <div class="schedule-content">
          <div>
            <h3>${api.escapeHtml(days[schedule.day_of_week] || "Dia")}</h3>
            <p>${api.escapeHtml(api.formatTime(schedule.start_time))} - ${api.escapeHtml(api.formatTime(schedule.end_time))}</p>
          </div>
          ${requestSummary}
        </div>
        <span class="badge badge-blue">${api.escapeHtml(schedule.notes || "Asignado")}</span>
        <button type="button" class="schedule-change-toggle" data-id="${schedule.id}" ${pendingRequest ? "disabled" : ""}>
          ${pendingRequest ? "En revision" : "Solicitar cambio"}
        </button>
        <form class="schedule-change-form" data-id="${schedule.id}" hidden>
          <div class="change-form-grid">
            <label>
              Inicio
              <input type="time" name="start_time" value="${api.escapeHtml(api.formatTime(schedule.start_time))}" required />
            </label>
            <label>
              Fin
              <input type="time" name="end_time" value="${api.escapeHtml(api.formatTime(schedule.end_time))}" required />
            </label>
            <label>
              Notas
              <input type="text" name="notes" maxlength="255" value="${api.escapeHtml(schedule.notes || "")}" />
            </label>
          </div>
          <label>
            Motivo
            <textarea name="message" maxlength="500" required placeholder="Explica el cambio que necesitas"></textarea>
          </label>
          <div class="change-form-actions">
            <button type="button" class="schedule-change-cancel">Cancelar</button>
            <button type="submit">Enviar solicitud</button>
          </div>
          <p class="schedule-change-message" aria-live="polite"></p>
        </form>
      </article>
    `
  }

  async function showProfessionalAlert(message, options = {}) {
    if (typeof window.showAdminAlert === "function") {
      await window.showAdminAlert(message, options)
      return
    }

    console.warn(message)
  }

  async function showProfessionalConfirm(message, options = {}) {
    if (typeof window.showAdminConfirm === "function") {
      return window.showAdminConfirm(message, options)
    }

    console.warn(message, options)
    return false
  }

  function statusLabel(status) {
    if (status === "approved") return "Aprobada"
    if (status === "rejected") return "Rechazada"
    return "Pendiente"
  }

  function statusClass(status) {
    if (status === "approved") return "badge-success"
    if (status === "rejected") return "severity-high"
    return "badge-warning"
  }

  function formatDate(value) {
    if (!value) return "Sin fecha"
    const [year, month, day] = value.split("-")
    return `${day}/${month}/${year}`
  }

  function renderVacationRequest(request) {
    return `
      <article class="vacation-row">
        <div>
          <h3>${api.escapeHtml(formatDate(request.start_date))} - ${api.escapeHtml(formatDate(request.end_date))}</h3>
          <p>${api.escapeHtml(request.reason)}</p>
        </div>
        <span class="badge ${statusClass(request.status)}">${api.escapeHtml(statusLabel(request.status))}</span>
      </article>
    `
  }

  async function loadSchedules() {
    const list = document.getElementById("professionalSchedulesList")
    if (!list) return

    try {
      const data = await api.fetchJson("/professional/schedules")
      list.innerHTML = data.schedules?.length
        ? data.schedules.map(renderSchedule).join("")
        : api.renderEmpty("No tienes turnos asignados por ahora.")
    } catch (error) {
      list.innerHTML = api.renderEmpty(error.message)
    }
  }

  async function loadVacationRequests() {
    const list = document.getElementById("vacationRequestsList")
    if (!list) return

    try {
      const data = await api.fetchJson("/professional/vacation-requests")
      list.innerHTML = data.vacation_requests?.length
        ? data.vacation_requests.map(renderVacationRequest).join("")
        : api.renderEmpty("No has enviado solicitudes de vacaciones.")
    } catch (error) {
      list.innerHTML = api.renderEmpty(error.message)
    }
  }

  function toggleChangeForm(button) {
    const row = button.closest(".schedule-row")
    const form = row?.querySelector(".schedule-change-form")
    if (!form) return
    form.hidden = !form.hidden
  }

  async function submitChangeRequest(form) {
    const scheduleId = form.dataset.id
    const message = form.querySelector(".schedule-change-message")
    message.textContent = ""
    message.classList.remove("is-error")

    const confirmed = await showProfessionalConfirm("Deseas enviar esta solicitud de cambio de turno?", {
      title: "Enviar solicitud",
      confirmText: "Enviar",
      cancelText: "Cancelar",
      variant: "info",
    })

    if (!confirmed) return

    try {
      const payload = {
        start_time: form.elements.start_time.value,
        end_time: form.elements.end_time.value,
        notes: form.elements.notes.value.trim() || null,
        message: form.elements.message.value.trim(),
      }

      const data = await api.fetchJson(`/schedules/${scheduleId}/change-request`, {
        method: "POST",
        body: JSON.stringify(payload),
      })

      const successMessage = data.message || "Solicitud enviada correctamente."
      message.textContent = successMessage
      await loadSchedules()
      await showProfessionalAlert(successMessage, {
        title: "Solicitud enviada",
        variant: "info",
      })
    } catch (error) {
      message.textContent = error.message
      message.classList.add("is-error")
      await showProfessionalAlert(error.message, {
        title: "No se pudo enviar",
        variant: "error",
      })
    }
  }

  async function submitVacationRequest(event) {
    event.preventDefault()

    const form = event.currentTarget
    const message = document.getElementById("vacationMessage")
    message.textContent = ""
    message.classList.remove("is-error")

    const confirmed = await showProfessionalConfirm("Deseas enviar esta solicitud de vacaciones?", {
      title: "Enviar solicitud",
      confirmText: "Enviar",
      cancelText: "Cancelar",
      variant: "info",
    })

    if (!confirmed) return

    try {
      const data = await api.fetchJson("/professional/vacation-requests", {
        method: "POST",
        body: JSON.stringify({
          start_date: document.getElementById("vacationStartDate").value,
          end_date: document.getElementById("vacationEndDate").value,
          reason: document.getElementById("vacationReason").value.trim(),
        }),
      })

      const successMessage = data.message || "Solicitud enviada correctamente."
      message.textContent = successMessage
      form.reset()
      await loadVacationRequests()
      await showProfessionalAlert(successMessage, {
        title: "Solicitud enviada",
        variant: "info",
      })
    } catch (error) {
      message.textContent = error.message
      message.classList.add("is-error")
      await showProfessionalAlert(error.message, {
        title: "No se pudo enviar",
        variant: "error",
      })
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    loadSchedules()
    loadVacationRequests()

    const vacationForm = document.getElementById("vacationForm")
    if (vacationForm) {
      vacationForm.addEventListener("submit", submitVacationRequest)
    }
  })
  document.addEventListener("click", (event) => {
    const toggle = event.target.closest(".schedule-change-toggle")
    if (toggle) {
      toggleChangeForm(toggle)
      return
    }

    const cancel = event.target.closest(".schedule-change-cancel")
    if (cancel) {
      const form = cancel.closest(".schedule-change-form")
      if (form) form.hidden = true
    }
  })

  document.addEventListener("submit", (event) => {
    const form = event.target.closest(".schedule-change-form")
    if (!form) return
    event.preventDefault()
    submitChangeRequest(form)
  })
})()
