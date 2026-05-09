const adminReminderText = document.getElementById("adminReminderText")
const adminDefaultPageButton = document.getElementById("adminDefaultPageButton")

const adminHomeData = {
  reminder: "Reunion a las 3 PM"
}

const adminDefaultPageLabels = {
  "./dashboard.html": "Dashboard",
  "./adultos-mayores.html": "Adultos mayores",
  "./users.html": "Usuarios",
  "./shifts.html": "Turnos",
  "./routines.html": "Rutinas",
  "./incidents.html": "Incidentes",
}

if (adminReminderText) {
  adminReminderText.textContent = adminHomeData.reminder
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
