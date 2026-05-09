(() => {
  const api = window.ProfessionalCare

  function renderMetric(value, label, icon) {
    return `
      <article class="professional-stat">
        <i class="${icon}"></i>
        <strong>${api.escapeHtml(value)}</strong>
        <span>${api.escapeHtml(label)}</span>
      </article>
    `
  }

  function renderAdult(adult) {
    return `
      <article class="professional-row">
        <span class="row-icon"><i class="bx bxs-user-detail"></i></span>
        <div>
          <h3>${api.escapeHtml(adult.full_name || "Adulto mayor")}</h3>
          <p>${api.escapeHtml(adult.age || "Sin edad")} anios &middot; ${api.escapeHtml(adult.room || "Sin habitacion")}</p>
        </div>
        <span class="badge ${api.getStatusClass(adult.status)}">${api.escapeHtml(adult.status || "Estado")}</span>
      </article>
    `
  }

  function renderMedication(item) {
    return `
      <article class="professional-row">
        <span class="row-icon"><i class="bx bxs-capsule"></i></span>
        <div>
          <h3>${api.escapeHtml(item.medication_name || "Medicamento")}</h3>
          <p>${api.escapeHtml(item.older_adult_name || "Adulto mayor")} &middot; ${api.escapeHtml(item.schedule || "Sin horario")}</p>
        </div>
        <span class="badge ${item.administered_today ? "badge-success" : "badge-warning"}">
          ${item.administered_today ? "Administrado" : "Pendiente"}
        </span>
      </article>
    `
  }

  function renderIncident(incident) {
    return `
      <article class="professional-row">
        <span class="row-icon"><i class="bx bxs-error"></i></span>
        <div>
          <h3>${api.escapeHtml(incident.title || "Incidente")}</h3>
          <p>${api.escapeHtml(incident.adult_name || "Adulto mayor")} &middot; ${api.escapeHtml(api.formatTime(incident.incident_time))}</p>
        </div>
        <span class="badge ${api.getSeverityClass(incident.severity)}">${api.escapeHtml(incident.severity || "media")}</span>
      </article>
    `
  }

  function renderDashboard(data) {
    const stats = data.stats || {}
    const metrics = document.getElementById("professionalMetrics")
    const adults = document.getElementById("professionalAdultsPreview")
    const routine = document.getElementById("professionalRoutinePreview")
    const incidents = document.getElementById("professionalIncidentsPreview")

    api.setText("professionalDashboardDate", api.formatDate(data.date), "Hoy")

    if (metrics) {
      metrics.innerHTML = [
        renderMetric(stats.older_adults ?? 0, "Adultos asignados", "bx bxs-id-card"),
        renderMetric(stats.medications_today ?? 0, "Medicinas hoy", "bx bxs-capsule"),
        renderMetric(stats.incidents_today ?? 0, "Incidentes hoy", "bx bxs-error"),
        renderMetric(stats.schedules ?? 0, "Turnos semanales", "bx bxs-time"),
      ].join("")
    }

    if (adults) {
      adults.innerHTML = data.older_adults?.length
        ? data.older_adults.map(renderAdult).join("")
        : api.renderEmpty("No tienes adultos mayores asignados.")
    }

    if (routine) {
      routine.innerHTML = data.next_medications?.length
        ? data.next_medications.map(renderMedication).join("")
        : api.renderEmpty("No hay medicinas programadas para hoy.", "bx bx-check-circle")
    }

    if (incidents) {
      incidents.innerHTML = data.incidents?.length
        ? data.incidents.map(renderIncident).join("")
        : api.renderEmpty("No hay incidentes hoy.", "bx bx-check-circle")
    }
  }

  async function loadDashboard() {
    try {
      const data = await api.fetchJson("/professional/overview")
      renderDashboard(data)
      window.CareNotifications?.handleData("professional", data, false)
    } catch (error) {
      document.querySelectorAll(".professional-list").forEach((element) => {
        element.innerHTML = api.renderEmpty(error.message)
      })
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    window.CareNotifications?.init({
      role: "professional",
      endpoint: "/professional/overview",
      fetchJson: api.fetchJson,
      mountSelector: ".professional-actions",
      routineUrl: "./routines.html",
    })
    loadDashboard()
  })
})()
