(() => {
  const api = window.ProfessionalCare
  const days = {
    0: "Domingo",
    1: "Lunes",
    2: "Martes",
    3: "Miércoles",
    4: "Jueves",
    5: "Viernes",
    6: "Sábado",
  }

  function renderSchedule(schedule) {
    const el = window.CuidadoUi.element
    const pending = schedule.change_request?.status === "pending"
    const content = el("div", "schedule-content", null, [el("div", "", null, [
      el("h3", "", days[schedule.day_of_week] || "Día"), el("p", "", `${api.formatTime(schedule.start_time)} - ${api.formatTime(schedule.end_time)}`),
    ])])
    if (pending) content.append(el("div", "change-request-summary", null, [
      el("strong", "", "Solicitud pendiente"), el("span", "", `${api.formatTime(schedule.change_request.start_time)} - ${api.formatTime(schedule.change_request.end_time)}`),
      el("p", "", schedule.change_request.message || ""),
    ]))
    const toggle = el("button", "schedule-change-toggle", pending ? "En revisión" : "Solicitar cambio")
    toggle.type = "button"
    toggle.disabled = pending
    toggle.dataset.id = String(schedule.id ?? "")
    const form = el("form", "schedule-change-form")
    form.dataset.id = String(schedule.id ?? "")
    form.hidden = true
    const grid = el("div", "change-form-grid")
    for (const [name, title, type, value] of [
      ["start_time", "Inicio", "time", api.formatTime(schedule.start_time)], ["end_time", "Fin", "time", api.formatTime(schedule.end_time)], ["notes", "Notas", "text", schedule.notes || ""],
    ]) {
      const input = document.createElement("input")
      input.name = name
      input.type = type
      input.value = value
      input.required = name !== "notes"
      if (name === "notes") input.maxLength = 255
      grid.append(el("label", "", title, [input]))
    }
    const message = document.createElement("textarea")
    message.name = "message"
    message.maxLength = 500
    message.required = true
    message.placeholder = "Explica el cambio que necesitas"
    const cancel = el("button", "schedule-change-cancel", "Cancelar")
    cancel.type = "button"
    const submit = el("button", "", "Enviar solicitud")
    submit.type = "submit"
    const feedback = el("p", "schedule-change-message")
    feedback.setAttribute("aria-live", "polite")
    form.append(grid, el("label", "", "Motivo", [message]), el("div", "change-form-actions", null, [cancel, submit]), feedback)
    return el("article", "professional-row schedule-row", null, [
      el("span", "row-icon", null, [el("i", "bx bxs-time")]), content, el("span", "badge badge-blue", schedule.notes || "Asignado"), toggle, form,
    ])
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

  const formatDate = window.CuidadoUi.formatNumericDate

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
      list.replaceChildren(...(data.schedules?.length ? data.schedules.map(renderSchedule) : [window.CuidadoUi.element("div", "empty-state", "No tienes turnos asignados por ahora.")]))
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

      const data = await api.fetchJson(`/schedules/${encodeURIComponent(scheduleId)}/change-request`, {
        method: "POST",
        body: JSON.stringify(payload),
      })

      const successMessage = data.message || "Solicitud enviada correctamente."
      message.textContent = successMessage
      await loadSchedules()
      await showProfessionalAlert(successMessage, {
        title: "Solicitud enviada",
        variant: "success",
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
        variant: "success",
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
