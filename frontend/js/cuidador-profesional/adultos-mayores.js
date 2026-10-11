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
        <a href="./routines.html?older_adult_id=${encodeURIComponent(String(adult.id ?? ""))}" class="professional-card-link">Ver rutina</a>
      </article>
    `
  }

  async function loadAdults(page = 1) {
    const list = document.getElementById("professionalAdultsList")
    if (!list) return

    try {
      const data = await api.fetchJson(window.CuidadoUi.pagePath("/professional/older-adults", Number.isInteger(page) ? page : 1))
      list.innerHTML = data.older_adults?.length
        ? data.older_adults.map(renderAdult).join("")
        : api.renderEmpty("No tienes adultos mayores asignados por ahora.")
      window.CuidadoUi.pagination(list, data.pagination, loadAdults)
    } catch (error) {
      list.innerHTML = api.renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", loadAdults)
})()
