const dashboardData = {
  stats: {
    olderAdults: 15,
    caregivers: 8,
    incidents: 2,
    requests: 5,
  },
  medicines: {
    pendingToday: 0,
  },
  report: {
    lateEntries: 1,
    absences: 0,
    vacations: 3,
    changes: 1,
  },
}

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
      const destination = `./routines.html?older_adult_id=${encodeURIComponent(routine.older_adult_id || routine.adulto_mayor_id || "")}`

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

    if (olderAdultsCount) {
      olderAdultsCount.textContent = data.stats?.older_adults ?? dashboardData.stats.olderAdults
    }

    if (medicineCount) {
      medicineCount.textContent = data.medications?.pending_today ?? dashboardData.medicines.pendingToday
    }

    if (medicineText) {
      const pendingToday = data.medications?.pending_today ?? dashboardData.medicines.pendingToday
      medicineText.textContent = `${pendingToday} no se han administrado hoy`
    }
  } catch (error) {
    console.error("Error al cargar resumen del dashboard:", error)

    if (medicineText) {
      medicineText.textContent = "No se pudo cargar el estado de hoy"
    }
  }
}

if (olderAdultsCount) olderAdultsCount.textContent = dashboardData.stats.olderAdults
if (caregiversCount) caregiversCount.textContent = dashboardData.stats.caregivers
if (incidentsCount) incidentsCount.textContent = dashboardData.stats.incidents
if (requestsCount) requestsCount.textContent = dashboardData.stats.requests
if (medicineCount) medicineCount.textContent = dashboardData.medicines.pendingToday
if (lateEntriesCount) lateEntriesCount.textContent = dashboardData.report.lateEntries
if (absencesCount) absencesCount.textContent = dashboardData.report.absences
if (vacationsCount) vacationsCount.textContent = dashboardData.report.vacations
if (changesCount) changesCount.textContent = dashboardData.report.changes

loadDashboardSummary()
loadLatestRoutineChanges()
