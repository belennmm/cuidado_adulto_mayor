const adminReminderText = document.getElementById("adminReminderText")
const adminDefaultPageButton = document.getElementById("adminDefaultPageButton")
const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

const adminDefaultPageLabels = {
  "./dashboard.html": "Dashboard",
  "./adultos-mayores.html": "Adultos mayores",
  "./users.html": "Usuarios",
  "./shifts.html": "Turnos",
  "./routines.html": "Rutinas",
  "./incidents.html": "Incidentes",
}

function getToken() {
  return localStorage.getItem("token")
}

async function loadAdminReminder() {
  if (!adminReminderText) return

  try {
    const token = getToken()
    const response = await fetch(`${API_URL}/dashboard-summary`, {
      cache: "no-store",
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    })
    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(data.message || "No se pudo cargar el estado de hoy.")
    }

    const pendingMeds = data.medications?.pending_today ?? 0
    const requests = data.stats?.requests ?? 0
    const incidents = data.stats?.incidents_today ?? 0

    adminReminderText.textContent = `${pendingMeds} medicamentos pendientes, ${requests} solicitudes y ${incidents} incidentes hoy`
  } catch (error) {
    adminReminderText.textContent = error.message
  }
}

if (adminDefaultPageButton) {
  try {
    const settings = JSON.parse(localStorage.getItem("adminPanelSettings") || "{}")
    const defaultPage = settings.defaultPage || "./dashboard.html"

    if (adminDefaultPageLabels[defaultPage]) {
      adminDefaultPageButton.href = defaultPage
      adminDefaultPageButton.textContent = adminDefaultPageLabels[defaultPage]
    }
  } catch (error) {
    adminDefaultPageButton.href = "./dashboard.html"
    adminDefaultPageButton.textContent = "Dashboard"
  }
}

loadAdminReminder()
