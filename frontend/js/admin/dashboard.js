const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

const olderAdultsCount = document.getElementById("olderAdultsCount")
const caregiversCount = document.getElementById("caregiversCount")
const incidentsCount = document.getElementById("incidentsCount")
const requestsCount = document.getElementById("requestsCount")
const medicineCount = document.getElementById("medicineCount")
const lateEntriesCount = document.getElementById("lateEntriesCount")
const absencesCount = document.getElementById("absencesCount")
const vacationsCount = document.getElementById("vacationsCount")
const changesCount = document.getElementById("changesCount")
const routineList = document.getElementById("routineList")
const medicineText = document.querySelector(".medicine-text")

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

async function fetchJson(path, options = {}) {
  const token = getToken()
  const response = await fetch(`${API_URL}${path}`, {
    cache: "no-store",
    ...options,
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(data.message || "No se pudo cargar la informacion.")
  }

  return data
}

function formatShortDate(value) {
  if (!value) return "Sin fecha"
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return "Sin fecha"
  }

  return date.toLocaleDateString("es-GT", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  })
}

function renderRoutineEmpty(message) {
  if (!routineList) return

  routineList.innerHTML = `
    <div class="routine-empty-state">
      ${escapeHtml(message)}
    </div>
  `
}

function renderLatestRoutines(routines) {
  if (!routineList) return

  if (!routines.length) {
    renderRoutineEmpty("Todavia no hay cambios registrados en rutinas.")
    return
  }

  routineList.innerHTML = routines
    .map((routine) => {
      const adult = routine.adulto_mayor?.full_name || "Adulto mayor"
      const activities = Array.isArray(routine.actividades) ? routine.actividades.length : 0
      const olderAdultId = routine.older_adult_id || routine.adulto_mayor_id || ""
      const destination = `./routines.html?older_adult_id=${encodeURIComponent(olderAdultId)}`

      return `
        <a class="routine-item routine-link-item" href="${destination}">
          <div class="routine-avatar">
            <i class="bx bxs-star"></i>
          </div>
          <div class="routine-content">
            <div class="routine-line-group">
              <strong>${escapeHtml(routine.nombre || "Rutina")}</strong>
              <span>${escapeHtml(adult)}</span>
            </div>
            <div class="routine-line-group">
              <strong>${escapeHtml(routine.horario || "Sin horario")}</strong>
              <span>${activities} actividades</span>
            </div>
            <div class="routine-line-group">
              <strong>${formatShortDate(routine.updated_at || routine.created_at)}</strong>
              <span>Ultima actualizacion</span>
            </div>
          </div>
        </a>
      `
    })
    .join("")
}

async function loadLatestRoutineChanges() {
  if (!routineList) return

  renderRoutineEmpty("Cargando cambios de rutinas...")

  try {
    const data = await fetchJson("/rutinas")
    const routines = (data.rutinas || [])
      .slice()
      .sort((first, second) => {
        const firstDate = new Date(first.updated_at || first.created_at || 0).getTime()
        const secondDate = new Date(second.updated_at || second.created_at || 0).getTime()
        return secondDate - firstDate
      })
      .slice(0, 4)

    renderLatestRoutines(routines)
  } catch (error) {
    renderRoutineEmpty(error.message)
  }
}

async function loadDashboardSummary() {
  try {
    const data = await fetchJson("/dashboard-summary")

    if (olderAdultsCount) olderAdultsCount.textContent = data.stats?.older_adults ?? 0
    if (caregiversCount) caregiversCount.textContent = data.stats?.caregivers ?? 0
    if (incidentsCount) incidentsCount.textContent = data.stats?.incidents_today ?? 0
    if (requestsCount) requestsCount.textContent = data.stats?.requests ?? 0
    if (medicineCount) medicineCount.textContent = data.medications?.pending_today ?? 0

    if (medicineText) {
      const pendingToday = data.medications?.pending_today ?? 0
      medicineText.textContent = `${pendingToday} no se han administrado hoy`
    }

    if (lateEntriesCount) lateEntriesCount.textContent = data.report?.late_entries ?? 0
    if (absencesCount) absencesCount.textContent = data.report?.absences ?? 0
    if (vacationsCount) vacationsCount.textContent = data.report?.vacation_requests ?? 0
    if (changesCount) changesCount.textContent = data.report?.change_requests ?? 0
  } catch (error) {
    console.error("Error al cargar resumen del dashboard:", error)

    if (medicineText) {
      medicineText.textContent = "No se pudo cargar el estado de hoy"
    }
  }
}

if (olderAdultsCount) olderAdultsCount.textContent = "0"
if (caregiversCount) caregiversCount.textContent = "0"
if (incidentsCount) incidentsCount.textContent = "0"
if (requestsCount) requestsCount.textContent = "0"
if (medicineCount) medicineCount.textContent = "0"
if (lateEntriesCount) lateEntriesCount.textContent = "0"
if (absencesCount) absencesCount.textContent = "0"
if (vacationsCount) vacationsCount.textContent = "0"
if (changesCount) changesCount.textContent = "0"

loadDashboardSummary()
loadLatestRoutineChanges()
