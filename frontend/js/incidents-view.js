(() => {
  function create({ incidentsList, incidentsCount, escapeHtml, formatDate, formatTime }) {
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
  
    return Object.freeze({ renderEmpty, renderIncidents })
  }
  window.IncidentsView = Object.freeze({ create })
})()

