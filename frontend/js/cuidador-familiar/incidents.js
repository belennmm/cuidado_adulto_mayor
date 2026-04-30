(() => {
  const api = window.FamilyCare
  const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

  function getToken() {
    return localStorage.getItem("token")
  }

  function formatShortDate(value) {
    if (!value) return "Sin fecha"
    const [year, month, day] = value.split("-")
    return `${day}/${month}/${year}`
  }

  function setText(id, value) {
    const element = document.getElementById(id)
    if (!element) return
    element.textContent = value || ""
  }

  function renderEmpty(message = "No se encuentran incidentes") {
    const list = document.getElementById("incidentsList")
    if (!list) return
    list.innerHTML = `<div class="incidents-empty-prototype">${api.escapeHtml(message)}</div>`
  }

  function renderLastIncident(incident) {
    if (!incident) {
      setText("lastIncidentStatus", "Resuelto")
      setText("lastIncidentAdult", "Sin incidentes")
      setText("lastIncidentDate", "Sin fecha")
      return
    }

    setText("lastIncidentStatus", incident.status || "Registrado")
    setText("lastIncidentAdult", incident.adult_name || "Adulto mayor")
    setText("lastIncidentDate", formatShortDate(incident.incident_date))
  }

  function renderIncidents(incidents) {
    const list = document.getElementById("incidentsList")
    if (!list) return

    renderLastIncident(incidents[0])

    if (!incidents.length) {
      renderEmpty()
      return
    }

    list.innerHTML = incidents.map((incident) => `
      <article class="incident-prototype-card">
        <h2>${api.escapeHtml(incident.title || "Incidente")}</h2>
        <p>${api.escapeHtml(incident.description || "Sin descripcion registrada.")}</p>
        <p>${api.escapeHtml(incident.adult_name || "Adulto mayor")} | ${api.escapeHtml(incident.status || "abierto")}</p>
      </article>
    `).join("")
  }

  async function loadIncidents() {
    const token = getToken()
    if (!token) {
      renderEmpty("Inicia sesion para ver incidentes")
      return
    }

    try {
      const response = await fetch(`${API_URL}/incidents/today`, {
        cache: "no-store",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.message || "No se pudieron cargar los incidentes.")
      }

      renderIncidents(data.incidents || [])
    } catch (error) {
      renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("incidentDetailButton")?.addEventListener("click", () => {
      alert("Detalle de incidente disponible cuando exista un registro del dia.")
    })

    loadIncidents()
  })
})()
