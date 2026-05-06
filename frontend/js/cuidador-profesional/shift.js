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

      message.textContent = data.message || "Solicitud enviada correctamente."
      await loadSchedules()
    } catch (error) {
      message.textContent = error.message
      message.classList.add("is-error")
    }
  }

  document.addEventListener("DOMContentLoaded", loadSchedules)
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
