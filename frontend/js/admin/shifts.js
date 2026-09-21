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

const state = { caregivers: [], schedules: [], vacations: [] }

const ADMIN_REQUEST_OPTIONS = Object.freeze({
  expectedRoles: ["admin"],
  fallbackError: "No se pudo completar la acción.",
})

const escapeHtml = window.CuidadoUi.escapeHtml

function setMessage(message, isError = false) {
  window.CuidadoUi.setMessage(shiftMessage, message, {
    type: isError ? "error" : "",
  })
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

const formatDate = window.CuidadoUi.formatNumericDate

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
  state.caregivers = data.users || []
  renderCaregiverOptions()
}

async function loadSchedules() {
  const data = await window.CuidadoApi.fetchJson("/admin/schedules", ADMIN_REQUEST_OPTIONS)
  state.schedules = data.schedules || []
  renderSchedules()
}

async function loadVacations() {
  const data = await window.CuidadoApi.fetchJson("/admin/vacation-requests", ADMIN_REQUEST_OPTIONS)
  state.vacations = data.vacation_requests || []
  renderVacations()
}

const { renderCaregiverOptions, renderVacations, renderSchedules, renderChangeRequest } = window.AdminShiftsView.create({ state, caregiverSelect, shiftsTableBody, vacationsTableBody, DAY_LABELS, escapeHtml, formatDate, formatTimeRange, statusLabel })

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
