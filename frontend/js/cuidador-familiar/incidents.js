(() => {
  const api = window.FamilyCare
  let selectedIncident = null
  let assignedAdults = []

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

  function getIncidentAdultName(incident) {
    return incident?.older_adult?.full_name || incident?.adult_name || "Adulto mayor"
  }

  function getRequestedAdultId() {
    const params = new URLSearchParams(window.location.search)
    return params.get("older_adult_id") || params.get("id") || ""
  }

  function updateAdultUrl(adultId) {
    if (!adultId) return
    const url = new URL(window.location.href)
    url.searchParams.set("older_adult_id", adultId)
    window.history.replaceState({}, "", url)
  }

  function getReporterName(incident) {
    return incident?.reporter?.name || incident?.reported_by || "Sin reportero"
  }

  function renderAdultSelector(selectedId) {
    const wrapper = document.getElementById("incidentsAdultSelectorWrapper")
    const selector = document.getElementById("incidentsAdultSelector")
    if (!wrapper || !selector) return

    wrapper.hidden = assignedAdults.length <= 1
    selector.innerHTML = assignedAdults
      .map((adult) => `
        <option value="${api.escapeHtml(adult.id)}">
          ${api.escapeHtml(adult.full_name || "Adulto mayor")}
        </option>
      `)
      .join("")

    if (selectedId) selector.value = String(selectedId)
  }

  function formatIncidentDetail(incident) {
    if (!incident) return "No hay incidente seleccionado."

    return [
      `Incidente: ${incident.title || "Incidente"}`,
      `Adulto mayor: ${getIncidentAdultName(incident)}`,
      `Estado: ${incident.status || "abierto"}`,
      `Severidad: ${incident.severity || "media"}`,
      `Fecha: ${formatShortDate(incident.incident_date)}`,
      `Hora: ${api.formatTime(incident.incident_time)}`,
      `Reportado por: ${getReporterName(incident)}`,
      `Detalle: ${incident.description || "Sin descripcion registrada."}`,
    ].join("\n")
  }

  function renderEmpty(message = "No se encuentran incidentes") {
    const list = document.getElementById("incidentsList")
    if (!list) return
    renderLastIncident(null)
    list.innerHTML = `<div class="incidents-empty-prototype">${api.escapeHtml(message)}</div>`
  }

  function renderLastIncident(incident) {
    selectedIncident = incident || null

    if (!incident) {
      setText("lastIncidentStatus", "Resuelto")
      setText("lastIncidentAdult", "Sin incidentes")
      setText("lastIncidentDate", "Sin fecha")
      return
    }

    setText("lastIncidentStatus", incident.status || "Registrado")
    setText("lastIncidentAdult", getIncidentAdultName(incident))
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
      <article class="incident-prototype-card" data-incident-id="${api.escapeHtml(incident.id)}" role="button" tabindex="0">
        <h2>${api.escapeHtml(incident.title || "Incidente")}</h2>
        <p>${api.escapeHtml(incident.description || "Sin descripcion registrada.")}</p>
        <p>
          ${api.escapeHtml(getIncidentAdultName(incident))} |
          ${api.escapeHtml(incident.status || "abierto")} |
          ${api.escapeHtml(incident.severity || "media")} |
          ${api.escapeHtml(api.formatTime(incident.incident_time))}
        </p>
        <p>Reportado por: ${api.escapeHtml(getReporterName(incident))}</p>
      </article>
    `).join("")

    list.querySelectorAll(".incident-prototype-card").forEach((card) => {
      const incident = incidents.find((item) => String(item.id) === card.dataset.incidentId)
      if (!incident) return

      const selectIncident = () => renderLastIncident(incident)
      card.addEventListener("click", selectIncident)
      card.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return
        event.preventDefault()
        selectIncident()
      })
    })
  }

  async function loadIncidentsForAdult(adultId) {
    const data = await api.fetchJson(`/family/older-adults/${encodeURIComponent(adultId)}/incidents`)
    renderIncidents(data.incidents || [])
    renderAdultSelector(adultId)
    updateAdultUrl(adultId)
  }

  async function loadIncidents() {
    try {
      const adultsData = await api.fetchJson("/family/older-adults")
      assignedAdults = adultsData.older_adults || []

      if (!assignedAdults.length) {
        renderEmpty("No tienes adultos mayores asignados por ahora.")
        return
      }

      const requestedAdultId = getRequestedAdultId()
      const selectedAdultId = assignedAdults.some((adult) => String(adult.id) === String(requestedAdultId))
        ? requestedAdultId
        : assignedAdults[0].id

      await loadIncidentsForAdult(selectedAdultId)
    } catch (error) {
      renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("incidentDetailButton")?.addEventListener("click", async () => {
      await api.showAlert(formatIncidentDetail(selectedIncident), {
        title: "Detalle del incidente",
        variant: selectedIncident ? "info" : "error",
      })
    })

    document.getElementById("incidentsAdultSelector")?.addEventListener("change", (event) => {
      loadIncidentsForAdult(event.target.value).catch((error) => renderEmpty(error.message))
    })

    loadIncidents()
  })
})()
