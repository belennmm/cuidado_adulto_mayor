const caregiverSelect = document.getElementById("caregiverSelect")
const daySelect = document.getElementById("daySelect")
const startTimeInput = document.getElementById("startTime")
const endTimeInput = document.getElementById("endTime")
const shiftNotesInput = document.getElementById("shiftNotes")
const shiftForm = document.getElementById("shiftForm")
const shiftMessage = document.getElementById("shiftMessage")
const shiftsTableBody = document.getElementById("shiftsTableBody")

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

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

function getToken() {
  return localStorage.getItem("token")
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;")
}

function getHeaders() {
  const token = getToken()

  return {
    "Accept": "application/json",
    "Content-Type": "application/json",
    ...(token ? { "Authorization": `Bearer ${token}` } : {}),
  }
}

function setMessage(message, isError = false) {
  shiftMessage.textContent = message
  shiftMessage.classList.toggle("is-error", isError)
}

function normalizeTime(value) {
  return String(value || "").slice(0, 5)
}

function formatTimeRange(schedule) {
  return `${normalizeTime(schedule.start_time)} - ${normalizeTime(schedule.end_time)}`
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      ...getHeaders(),
      ...(options.headers || {}),
    },
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(data.message || "No se pudo completar la accion.")
  }

  return data
}

async function loadCaregivers() {
  const data = await fetchJson(`${API_URL}/admin/professional-caregivers`)
  caregiversData = data.users || []
  renderCaregiverOptions()
}

async function loadSchedules() {
  const data = await fetchJson(`${API_URL}/admin/schedules`)
  schedulesData = data.schedules || []
  renderSchedules()
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

function renderSchedules() {
  shiftsTableBody.innerHTML = ""

  if (!schedulesData.length) {
    shiftsTableBody.innerHTML = `
      <div class="empty-state">
        Todavia no hay turnos asignados.
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

      <div class="shift-cell" data-label="Dia">
        ${escapeHtml(DAY_LABELS[schedule.day_of_week] || "Sin dia")}
      </div>

      <div class="shift-cell" data-label="Horario">
        ${escapeHtml(formatTimeRange(schedule))}
      </div>

      <div class="shift-cell" data-label="Notas">
        ${escapeHtml(schedule.notes || "Sin notas")}
      </div>

      <div class="shift-cell" data-label="Accion">
        <button type="button" class="delete-shift-button" data-id="${schedule.id}">
          Eliminar
        </button>
      </div>
    `

    shiftsTableBody.appendChild(row)
  })

  document.querySelectorAll(".delete-shift-button").forEach((button) => {
    button.addEventListener("click", () => deleteSchedule(button.dataset.id))
  })
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

    const data = await fetchJson(`${API_URL}/admin/schedules`, {
      method: "POST",
      body: JSON.stringify(payload),
    })

    setMessage(data.message || "Turno asignado correctamente.")
    shiftForm.reset()
    daySelect.value = "1"
    await loadSchedules()
  } catch (error) {
    setMessage(error.message, true)
  }
}

async function deleteSchedule(scheduleId) {
  if (!window.confirm("Seguro que deseas eliminar este turno?")) {
    return
  }

  try {
    const data = await fetchJson(`${API_URL}/admin/schedules/${scheduleId}`, {
      method: "DELETE",
    })

    setMessage(data.message || "Turno eliminado correctamente.")
    await loadSchedules()
  } catch (error) {
    setMessage(error.message, true)
  }
}

async function initShiftsPage() {
  try {
    await Promise.all([loadCaregivers(), loadSchedules()])
  } catch (error) {
    shiftsTableBody.innerHTML = `
      <div class="empty-state">
        ${escapeHtml(error.message)}
      </div>
    `
  }
}

if (shiftForm) {
  shiftForm.addEventListener("submit", saveSchedule)
}

initShiftsPage()
