(() => {
  const api = window.ProfessionalCare

  function statusLabel(item) {
    if (item.administered_today) return "Administrado"
    if (item.due_today) return "Pendiente"
    return "Programado"
  }

  function renderRoutine(item) {
    return `
      <article class="professional-row">
        <span class="row-icon"><i class="bx bxs-capsule"></i></span>
        <div>
          <h3>${api.escapeHtml(item.medication_name || "Medicamento")}</h3>
          <p>${api.escapeHtml(item.older_adult_name || "Adulto mayor")} &middot; ${api.escapeHtml(item.schedule || "Sin horario")} &middot; ${api.escapeHtml(item.dosage || "Sin dosis")}</p>
        </div>
        <span class="badge ${item.administered_today ? "badge-success" : item.due_today ? "badge-warning" : "badge-blue"}">
          ${api.escapeHtml(statusLabel(item))}
        </span>
      </article>
    `
  }

  async function loadRoutines() {
    const list = document.getElementById("professionalRoutinesList")
    if (!list) return

    try {
      const data = await api.fetchJson("/professional/routines")
      list.innerHTML = data.routine?.length
        ? data.routine.map(renderRoutine).join("")
        : api.renderEmpty("No hay rutinas registradas para tus adultos asignados.")
    } catch (error) {
      list.innerHTML = api.renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", loadRoutines)
})()
