const incidentsList = document.getElementById("incidentsList")
const incidentsDate = document.getElementById("incidentsDate")
const incidentsCount = document.getElementById("incidentsCount")
const incidentsDateInput = document.getElementById("incidentsDateInput")

function getToken() {
    return (window.AuthSession?.getToken() || "")
}

async function showPopup(message, options = {}) {
    if (window.showAdminAlert) {
        await window.showAdminAlert(message, options)
        return
    }

    console.warn(message)
}

function getSearchDate() {
    const params = new URLSearchParams(window.location.search)
    const date = params.get("date")
    return date && /^\d{4}-\d{2}-\d{2}$/.test(date) ? date : null
}

function setSearchDate(date) {
    const url = new URL(window.location.href)
    if (date) {
        url.searchParams.set("date", date)
    } else {
        url.searchParams.delete("date")
    }

    window.history.replaceState({}, "", url)
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
        const message = "Inicia sesion para ver los incidentes del dia."
        renderEmpty(message)
        incidentsCount.textContent = "0"
        await showPopup(message, { variant: "error" })
        return
    }

    try {
        const selectedDate = incidentsDateInput?.value || getSearchDate()
        const params = new URLSearchParams()
        if (selectedDate) {
            params.set("date", selectedDate)
        }

        const data = await window.CuidadoApi.fetchJson(`/incidents${params.toString() ? `?${params.toString()}` : ""}`, {
            token,
            fallbackError: "No se pudieron cargar los incidentes.",
        })

        incidentsDate.textContent = formatDate(data.date)

        if (incidentsDateInput) {
            const dateValue = data.date || selectedDate
            if (dateValue) {
                incidentsDateInput.value = dateValue
                setSearchDate(dateValue)
            }
        }

        renderIncidents(data.incidents || [])
    } catch (error) {
        incidentsCount.textContent = "0"
        renderEmpty(error.message)
        await showPopup(error.message, { variant: "error" })
    }
}

if (incidentsDateInput) {
    incidentsDateInput.addEventListener("change", () => {
        const value = incidentsDateInput.value || ""
        setSearchDate(value)
        loadTodayIncidents()
    })
}

loadTodayIncidents()
