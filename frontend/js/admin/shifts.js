const caregiverSelect = document.getElementById("caregiverSelect")
const daySelect = document.getElementById("daySelect")
const startTimeInput = document.getElementById("startTime")
const endTimeInput = document.getElementById("endTime")
const shiftNotesInput = document.getElementById("shiftNotes")
const shiftForm = document.getElementById("shiftForm")
const shiftMessage = document.getElementById("shiftMessage")
const shiftsTableBody = document.getElementById("shiftsTableBody")
const vacationsTableBody = document.getElementById("vacationsTableBody")

const DAY_LABELS = {
  0: "Domingo",
  1: "Lunes",
  2: "Martes",
  3: "Miercoles",
  4: "Jueves",
  5: "Viernes",
  6: "Sabado",
}

let caregiversData = []
let schedulesData = []
let vacationsData = []

const ADMIN_REQUEST_OPTIONS = Object.freeze({
  expectedRoles: ["admin"],
  fallbackError: "No se pudo completar la acción.",
})

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;")
}

function setMessage(message, isError = false) {
  shiftMessage.textContent = message
  shiftMessage.classList.toggle("is-error", isError)
}

async function showPopup(message, options = {}) {
  if (window.showAdminAlert) {
    await window.showAdminAlert(message, options)
    return
  }

  console.warn(message)
}

function normalizeTime(value) {
  return String(value || "").slice(0, 5)
}

function formatDate(value) {
  if (!value) return "Sin fecha"
  const [year, month, day] = value.split("-")
  return `${day}/${month}/${year}`
}

function statusLabel(status) {
  if (status === "approved") return "Aprobada"
  if (status === "rejected") return "Rechazada"
  return "Pendiente"
}

function formatTimeRange(schedule) {
  return `${normalizeTime(schedule.start_time)} - ${normalizeTime(schedule.end_time)}`
}

async function loadCaregivers() {
  const data = await window.CuidadoApi.fetchJson("/admin/professional-caregivers", ADMIN_REQUEST_OPTIONS)
  caregiversData = data.users || []
  renderCaregiverOptions()
}

async function loadSchedules() {
  const data = await window.CuidadoApi.fetchJson("/admin/schedules", ADMIN_REQUEST_OPTIONS)
  schedulesData = data.schedules || []
  renderSchedules()
}

async function loadVacations() {
  const data = await window.CuidadoApi.fetchJson("/admin/vacation-requests", ADMIN_REQUEST_OPTIONS)
  vacationsData = data.vacation_requests || []
  renderVacations()
}

function renderCaregiverOptions() {
  caregiverSelect.innerHTML = `<option value="">Seleccionar cuidador</option>`

  caregiversData.forEach((caregiver) => {
    const option = document.createElement("option")
    option.value = caregiver.id
    option.textContent = `${caregiver.name} (${caregiver.email})`
    caregiverSelect.appendChild(option)
  })

  if (!caregiversData.length) {
    const option = document.createElement("option")
    option.value = ""
    option.textContent = "No hay cuidadores aprobados"
    caregiverSelect.appendChild(option)
  }
}

function renderVacations() {
  if (!vacationsTableBody) return
  vacationsTableBody.innerHTML = ""

  if (!vacationsData.length) {
    vacationsTableBody.innerHTML = `
      <div class="empty-state">
        Todavía no hay solicitudes de vacaciones.
      </div>
    `
    return
  }

  vacationsData.forEach((request) => {
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

  if (!schedulesData.length) {
    shiftsTableBody.innerHTML = `
      <div class="empty-state">
        Todavía no hay turnos asignados.
      </div>
    `
    return
  }

  schedulesData.forEach((schedule) => {
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

async function saveSchedule(event) {
  event.preventDefault()
  setMessage("")

  try {
    const payload = {
      user_id: Number(caregiverSelect.value),
      day_of_week: Number(daySelect.value),
      start_time: startTimeInput.value,
      end_time: endTimeInput.value,
      notes: shiftNotesInput.value.trim() || null,
    }

    if (!payload.user_id) {
      throw new Error("Selecciona un cuidador.")
    }

    const data = await window.CuidadoApi.fetchJson("/admin/schedules", {
      ...ADMIN_REQUEST_OPTIONS,
      method: "POST",
      body: JSON.stringify(payload),
    })

    const message = data.message || "Turno asignado correctamente."
    setMessage(message)
    shiftForm.reset()
    daySelect.value = "1"
    await loadSchedules()
    await showPopup(message, { variant: "success" })
  } catch (error) {
    setMessage(error.message, true)
    await showPopup(error.message, { variant: "error" })
  }
}

async function deleteSchedule(scheduleId) {
  const confirmed = window.showAdminConfirm
    ? await window.showAdminConfirm("Seguro que deseas eliminar este turno?", {
        title: "Eliminar turno",
        confirmText: "Eliminar",
      })
    : false

  if (!confirmed) {
    return
  }

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/schedules/${scheduleId}`, {
      ...ADMIN_REQUEST_OPTIONS,
      method: "DELETE",
    })

    const message = data.message || "Turno eliminado correctamente."
    setMessage(message)
    await loadSchedules()
    await showPopup(message, { variant: "success" })
  } catch (error) {
    setMessage(error.message, true)
    await showPopup(error.message, { variant: "error" })
  }
}

async function resolveChangeRequest(scheduleId, action) {
  const label = action === "approve" ? "aprobar" : "rechazar"
  const confirmed = window.showAdminConfirm
    ? await window.showAdminConfirm(`Seguro que deseas ${label} esta solicitud?`, {
        title: action === "approve" ? "Aprobar solicitud" : "Rechazar solicitud",
        confirmText: action === "approve" ? "Aprobar" : "Rechazar",
        variant: action === "approve" ? "info" : "danger",
      })
    : false

  if (!confirmed) {
    return
  }

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/schedules/${scheduleId}/change-request/${action}`, {
      ...ADMIN_REQUEST_OPTIONS,
      method: "PATCH",
    })

    const message = data.message || "Solicitud actualizada correctamente."
    setMessage(message)
    await loadSchedules()
    await showPopup(message, { variant: "success" })
  } catch (error) {
    setMessage(error.message, true)
    await showPopup(error.message, { variant: "error" })
  }
}

async function resolveVacationRequest(requestId, action) {
  const label = action === "approve" ? "aprobar" : "rechazar"
  const confirmed = window.showAdminConfirm
    ? await window.showAdminConfirm(`Seguro que deseas ${label} esta solicitud de vacaciones?`, {
        title: action === "approve" ? "Aprobar vacaciones" : "Rechazar vacaciones",
        confirmText: action === "approve" ? "Aprobar" : "Rechazar",
        variant: action === "approve" ? "info" : "danger",
      })
    : false

  if (!confirmed) {
    return
  }

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/vacation-requests/${requestId}/${action}`, {
      ...ADMIN_REQUEST_OPTIONS,
      method: "PATCH",
    })

    const message = data.message || "Solicitud de vacaciones actualizada."
    setMessage(message)
    await loadVacations()
    await showPopup(message, { variant: "success" })
  } catch (error) {
    setMessage(error.message, true)
    await showPopup(error.message, { variant: "error" })
  }
}

async function initShiftsPage() {
  try {
    await Promise.all([loadCaregivers(), loadSchedules(), loadVacations()])
  } catch (error) {
    shiftsTableBody.innerHTML = `
      <div class="empty-state">
        ${escapeHtml(error.message)}
      </div>
    `
    if (vacationsTableBody) {
      vacationsTableBody.innerHTML = `
        <div class="empty-state">
          ${escapeHtml(error.message)}
        </div>
      `
    }
  }
}

if (shiftForm) {
  shiftForm.addEventListener("submit", saveSchedule)
}

initShiftsPage()
