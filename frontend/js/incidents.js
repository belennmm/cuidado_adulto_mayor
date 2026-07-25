const incidentsList = document.getElementById("incidentsList")
const incidentsDate = document.getElementById("incidentsDate")
const incidentsCount = document.getElementById("incidentsCount")
const incidentsDateInput = document.getElementById("incidentsDateInput")
const registerIncidentButton = document.getElementById("registerIncidentButton")
const incidentFormModal = document.getElementById("incidentFormModal")
const incidentForm = document.getElementById("incidentForm")
const incidentOlderAdult = document.getElementById("incidentOlderAdult")
const incidentTitle = document.getElementById("incidentTitle")
const incidentDateField = document.getElementById("incidentDate")
const incidentTimeField = document.getElementById("incidentTime")
const incidentFormMessage = document.getElementById("incidentFormMessage")
const submitIncidentForm = document.getElementById("submitIncidentForm")

let assignedOlderAdultsLoaded = false

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

function getLocalDateAndTime() {
    const now = new Date()
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, "0")
    const day = String(now.getDate()).padStart(2, "0")
    const hours = String(now.getHours()).padStart(2, "0")
    const minutes = String(now.getMinutes()).padStart(2, "0")

    return {
        date: `${year}-${month}-${day}`,
        time: `${hours}:${minutes}`,
    }
}

function setIncidentFormMessage(message = "", variant = "error") {
    if (!incidentFormMessage) return

    incidentFormMessage.textContent = message
    incidentFormMessage.classList.toggle("is-success", variant === "success")
    incidentFormMessage.hidden = !message
}

async function loadAssignedOlderAdults() {
    if (assignedOlderAdultsLoaded || !incidentOlderAdult) return

    incidentOlderAdult.disabled = true
    incidentOlderAdult.innerHTML = '<option value="">Cargando adultos asignados...</option>'
    submitIncidentForm.disabled = true

    try {
        const data = await window.CuidadoApi.fetchJson("/professional/older-adults", {
            token: getToken(),
            fallbackError: "No se pudieron cargar los adultos mayores asignados.",
        })
        const olderAdults = Array.isArray(data.older_adults) ? data.older_adults : []

        incidentOlderAdult.innerHTML = `
            <option value="">Selecciona un adulto mayor</option>
            ${olderAdults.map((adult) => {
                const room = adult.room ? ` — Habitación ${adult.room}` : ""
                return `<option value="${escapeHtml(adult.id)}">${escapeHtml((adult.full_name || "Sin nombre") + room)}</option>`
            }).join("")}
        `
        incidentOlderAdult.disabled = olderAdults.length === 0
        submitIncidentForm.disabled = olderAdults.length === 0
        assignedOlderAdultsLoaded = olderAdults.length > 0

        if (!olderAdults.length) {
            setIncidentFormMessage("No tienes adultos mayores asignados. Solicita una asignación al administrador.")
        }
    } catch (error) {
        incidentOlderAdult.innerHTML = '<option value="">No se pudo cargar la lista</option>'
        incidentOlderAdult.disabled = true
        submitIncidentForm.disabled = true
        throw error
    }
}

async function openIncidentForm() {
    if (!incidentFormModal || !incidentForm) return

    incidentForm.reset()
    setIncidentFormMessage()
    submitIncidentForm.disabled = Boolean(incidentOlderAdult?.disabled)

    const current = getLocalDateAndTime()
    incidentDateField.value = incidentsDateInput?.value || current.date
    incidentTimeField.value = current.time
    incidentFormModal.hidden = false
    document.body.classList.add("incident-modal-open")

    try {
        await loadAssignedOlderAdults()
        incidentOlderAdult?.focus()
    } catch (error) {
        setIncidentFormMessage(error.message)
    }
}

function closeIncidentForm() {
    if (!incidentFormModal) return

    incidentFormModal.hidden = true
    document.body.classList.remove("incident-modal-open")
    setIncidentFormMessage()
    registerIncidentButton?.focus()
}

async function saveIncident(event) {
    event.preventDefault()

    if (!incidentForm?.reportValidity()) return

    const formData = new FormData(incidentForm)
    const payload = {
        older_adult_id: Number(formData.get("older_adult_id")),
        title: String(formData.get("title") || "").trim(),
        description: String(formData.get("description") || "").trim() || null,
        severity: String(formData.get("severity") || "media"),
        incident_date: String(formData.get("incident_date") || ""),
        incident_time: String(formData.get("incident_time") || ""),
    }

    submitIncidentForm.disabled = true
    const submitButtonContent = submitIncidentForm.innerHTML
    submitIncidentForm.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Guardando...'
    setIncidentFormMessage()
    let incidentWasSaved = false

    try {
        const data = await window.CuidadoApi.fetchJson("/professional/incidents", {
            method: "POST",
            token: getToken(),
            body: JSON.stringify(payload),
            fallbackError: "No se pudo registrar el incidente.",
        })
        incidentWasSaved = true

        setIncidentFormMessage(data.message || "Incidente registrado correctamente.", "success")
        incidentsDateInput.value = payload.incident_date
        setSearchDate(payload.incident_date)
        const listUpdated = await loadTodayIncidents(payload.incident_date)

        if (listUpdated) {
            window.setTimeout(() => closeIncidentForm(), 700)
        } else {
            setIncidentFormMessage(
                "El incidente se guardó, pero la lista no pudo actualizarse. Cierra el formulario e intenta recargar la página.",
                "success"
            )
        }
    } catch (error) {
        setIncidentFormMessage(error.message)
    } finally {
        submitIncidentForm.disabled = incidentWasSaved
        submitIncidentForm.innerHTML = submitButtonContent
    }
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

async function loadTodayIncidents(requestedDate = "") {
    const token = getToken()

    if (!token) {
        const message = "Inicia sesión para ver los incidentes del día."
        renderEmpty(message)
        incidentsCount.textContent = "0"
        await showPopup(message, { variant: "error" })
        return false
    }

    try {
        const selectedDate = requestedDate || incidentsDateInput?.value || getSearchDate()
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
        return true
    } catch (error) {
        incidentsCount.textContent = "0"
        renderEmpty(error.message)
        await showPopup(error.message, { variant: "error" })
        return false
    }
}

if (incidentsDateInput) {
    incidentsDateInput.addEventListener("change", () => {
        const value = incidentsDateInput.value || ""
        setSearchDate(value)
        loadTodayIncidents()
    })
}

registerIncidentButton?.addEventListener("click", openIncidentForm)
document.getElementById("closeIncidentForm")?.addEventListener("click", closeIncidentForm)
document.getElementById("cancelIncidentForm")?.addEventListener("click", closeIncidentForm)
incidentForm?.addEventListener("submit", saveIncident)

incidentFormModal?.addEventListener("click", (event) => {
    if (event.target === incidentFormModal) {
        closeIncidentForm()
    }
})

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && incidentFormModal && !incidentFormModal.hidden) {
        closeIncidentForm()
    }
})

loadTodayIncidents()
