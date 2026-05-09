(() => {
  const api = window.FamilyCare

  function renderMedicationRow(medication) {
    const statusClass = medication.administered_today ? "badge-success" : "badge-warning"
    const statusLabel = medication.administered_today
      ? `Administrado ${api.formatTime(medication.administered_time)}`
      : "Pendiente"

    return `
      <article class="mini-row">
        <span class="mini-icon"><i class="bx bxs-capsule"></i></span>
        <div>
          <h3 class="mini-title">${api.escapeHtml(medication.medication_name || "Medicamento")}</h3>
          <p class="mini-text">
            ${api.escapeHtml(medication.older_adult_name || "Adulto mayor")} &middot;
            ${api.escapeHtml(medication.schedule || "Sin horario")} &middot;
            ${api.escapeHtml(medication.dosage || "Sin dosis")}
          </p>
        </div>
        <span class="badge ${statusClass}">${api.escapeHtml(statusLabel)}</span>
      </article>
    `
  }

  function renderAdultRow(adult) {
    return `
      <article class="mini-row">
        <span class="mini-icon"><i class="bx bxs-user-detail"></i></span>
        <div>
          <h3 class="mini-title">${api.escapeHtml(adult.full_name || "Adulto mayor")}</h3>
          <p class="mini-text">
            ${api.escapeHtml(adult.age || "Sin edad")} anios &middot; ${api.escapeHtml(adult.room || "Sin habitacion")}
          </p>
        </div>
        <span class="badge ${api.getStatusClass(adult.status)}">${api.escapeHtml(adult.status || "Estado")}</span>
      </article>
    `
  }

  function renderIncident(incident) {
    return `
      <article class="incident-preview">
        <div class="adult-meta">
          <span class="badge ${api.getSeverityClass(incident.severity)}">${api.escapeHtml(incident.severity || "media")}</span>
          <span class="badge badge-blue">${api.escapeHtml(api.formatTime(incident.incident_time))}</span>
        </div>
        <h3>${api.escapeHtml(incident.title || "Incidente")}</h3>
        <p>${api.escapeHtml(incident.adult_name || "Adulto mayor")} &middot; ${api.escapeHtml(incident.status || "abierto")}</p>
      </article>
    `
  }

  function renderDashboard(data) {
    const stats = data.stats || {}

    api.setText("dashboardDate", api.formatDate(data.date), "Hoy")
    api.setText("dashboardAdultsCount", stats.older_adults ?? 0)
    api.setText("dashboardMedsCount", stats.medications_today ?? 0)
    api.setText("dashboardPendingCount", stats.pending_medications ?? 0)
    api.setText("dashboardIncidentsCount", stats.incidents_today ?? 0)
    api.setText("dashboardStableCount", stats.stable ?? 0)
    api.setText("dashboardAttentionCount", stats.attention ?? 0)
    api.setText("dashboardCriticalCount", stats.critical ?? 0)

    const routineList = document.getElementById("dashboardRoutineList")
    const incidentsList = document.getElementById("dashboardIncidentsList")
    const adultsList = document.getElementById("dashboardAdultsList")

    if (routineList) {
      routineList.innerHTML = data.next_medications?.length
        ? data.next_medications.map(renderMedicationRow).join("")
        : api.renderEmpty("No hay medicamentos programados para hoy.", "bx bx-check-circle")
    }

    if (incidentsList) {
      incidentsList.innerHTML = data.incidents?.length
        ? data.incidents.map(renderIncident).join("")
        : api.renderEmpty("No hay incidentes para tus familiares hoy.", "bx bx-check-circle")
    }

    if (adultsList) {
      adultsList.innerHTML = data.older_adults?.length
        ? data.older_adults.map(renderAdultRow).join("")
        : api.renderEmpty("No tienes adultos mayores asignados por ahora.")
    }
  }

  async function loadDashboard() {
    try {
      const data = await api.fetchJson("/family/overview")
      renderDashboard(data)
      window.CareNotifications?.handleData("family", data, true)
    } catch (error) {
      const containers = ["dashboardRoutineList", "dashboardIncidentsList", "dashboardAdultsList"]

      containers.forEach((id) => {
        const element = document.getElementById(id)
        if (element) element.innerHTML = api.renderEmpty(error.message)
      })
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    window.CareNotifications?.init({
      role: "family",
      endpoint: "/family/overview",
      fetchJson: api.fetchJson,
      mountSelector: ".family-page-header",
      routineUrl: "./routine.html",
    })
    loadDashboard()
  })
})()
