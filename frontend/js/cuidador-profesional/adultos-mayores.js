(() => {
  const api = window.ProfessionalCare

  function renderAdult(adult) {
    const meds = adult.medications_count ?? adult.medications?.length ?? 0

    return `
      <article class="professional-card">
        <div class="professional-card-top">
          <h2>${api.escapeHtml(adult.full_name || "Adulto mayor")}</h2>
          <span class="badge ${api.getStatusClass(adult.status)}">${api.escapeHtml(adult.status || "Estado")}</span>
        </div>
        <p>${api.escapeHtml(adult.age || "Sin edad")} anios &middot; ${api.escapeHtml(adult.room || "Sin habitacion")}</p>
        <p>Familiar: ${api.escapeHtml(adult.family_caregiver_name || "Sin familiar asignado")}</p>
        <p>Medicamentos activos: ${api.escapeHtml(meds)}</p>
      </article>
    `
  }

  async function loadAdults() {
    const list = document.getElementById("professionalAdultsList")
    if (!list) return

    try {
      const data = await api.fetchJson("/professional/older-adults")
      list.innerHTML = data.older_adults?.length
        ? data.older_adults.map(renderAdult).join("")
        : api.renderEmpty("No tienes adultos mayores asignados por ahora.")
    } catch (error) {
      list.innerHTML = api.renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", loadAdults)
})()
