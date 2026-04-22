const incidentsList = document.getElementById("incidentsList")
const incidentsDate = document.getElementById("incidentsDate")
const incidentsCount = document.getElementById("incidentsCount")

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

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

function formatDate(value) {
  if (!value) return "Hoy"

  const [year, month, day] = value.split("-")
  const date = new Date(Number(year), Number(month) - 1, Number(day))

  return new Intl.DateTimeFormat("es-GT", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(date)
}

function formatTime(value) {
  if (!value) return "Sin hora registrada"
  return value.slice(0, 5)
}

function getSeverityLabel(severity) {
  const labels = {
    baja: "Baja",
    media: "Media",
    alta: "Alta",
  }

  return labels[severity] || severity || "Sin prioridad"
}

function getSeverityClass(severity) {
  if (severity === "alta") return "incident-badge-high"
  if (severity === "baja") return "incident-badge-low"
  return "incident-badge-medium"
}

function renderEmpty(message) {
  incidentsList.innerHTML = `
    <div class="incidents-empty">
      ${escapeHtml(message)}
    </div>
  `
}

function renderIncidents(incidents) {
  incidentsList.innerHTML = ""
  incidentsCount.textContent = String(incidents.length)

  if (!incidents.length) {
    renderEmpty("No hay incidentes registrados para hoy.")
    return
  }

  incidents.forEach((incident) => {
    const card = document.createElement("article")
    card.className = "incident-card"

    card.innerHTML = `
      <div class="incident-card-top">
        <h2 class="incident-title">${escapeHtml(incident.title)}</h2>
        <span class="incident-badge incident-status">${escapeHtml(incident.status || "abierto")}</span>
      </div>

      <div class="incident-meta">
        <span class="incident-badge ${getSeverityClass(incident.severity)}">
          ${escapeHtml(getSeverityLabel(incident.severity))}
        </span>
        <span class="incident-badge incident-status">
          ${escapeHtml(formatTime(incident.incident_time))}
        </span>
      </div>

      <p class="incident-description">
        ${escapeHtml(incident.description || "Sin descripcion registrada.")}
      </p>

      <p class="incident-detail">
        <strong>Adulto mayor:</strong> ${escapeHtml(incident.adult_name || "No asignado")}
      </p>

      <p class="incident-detail">
        <strong>Reportado por:</strong> ${escapeHtml(incident.reported_by || "Sin responsable")}
      </p>
    `

    incidentsList.appendChild(card)
  })
}

async function loadTodayIncidents() {
  const token = getToken()

  if (!token) {
    renderEmpty("Inicia sesion para ver los incidentes del dia.")
    incidentsCount.textContent = "0"
    return
  }

  try {
    const response = await fetch(`${API_URL}/incidents/today`, {
      headers: {
        "Accept": "application/json",
        "Authorization": `Bearer ${token}`,
      },
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || "No se pudieron cargar los incidentes.")
    }

    incidentsDate.textContent = formatDate(data.date)
    renderIncidents(data.incidents || [])
  } catch (error) {
    incidentsCount.textContent = "0"
    renderEmpty(error.message)
  }
}

loadTodayIncidents()
