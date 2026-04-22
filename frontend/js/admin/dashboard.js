const dashboardData = {
  stats: {
    olderAdults: 15,
    caregivers: 8,
    incidents: 2,
    requests: 5
  },
  medicines: {
    pendingToday: 0
  },
  report: {
    lateEntries: 1,
    absences: 0,
    vacations: 3,
    changes: 1
  },
  latestRoutineChanges: [
    {},
    {},
    {},
    {}
  ]
}

const API_URL = "http://127.0.0.1:8080/api"

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

async function loadDashboardSummary() {
  try {
    const token = getToken()
    const response = await fetch(`${API_URL}/dashboard-summary`, {
      cache: "no-store",
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || "No se pudo cargar el dashboard.")
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

if (routineList) {
  routineList.innerHTML = ""

  dashboardData.latestRoutineChanges.forEach(() => {
    const item = document.createElement("article")
    item.className = "routine-item"

    item.innerHTML = `
      <div class="routine-avatar"></div>
      <div class="routine-content">
        <div class="routine-line-group">
          <div class="routine-line long"></div>
          <div class="routine-line short"></div>
        </div>
        <div class="routine-line-group">
          <div class="routine-line medium"></div>
          <div class="routine-line short"></div>
        </div>
        <div class="routine-line-group">
          <div class="routine-line medium"></div>
        </div>
      </div>
    `

    routineList.appendChild(item)
  })
}

loadDashboardSummary()
