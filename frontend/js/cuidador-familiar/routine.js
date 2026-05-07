(() => {
  const api = window.FamilyCare
  let assignedAdults = []

  function setValue(id, value) {
    const element = document.getElementById(id)
    if (!element) return
    element.value = value || ""
  }

  function setText(id, value) {
    const element = document.getElementById(id)
    if (!element) return
    element.textContent = value || ""
  }

  function firstName(fullName) {
    return String(fullName || "Adulto mayor").split(" ")[0]
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

  function formatInputDate(value) {
    if (!value) return ""
    const [year, month, day] = value.split("-")
    return `${day}/${month}/${year}`
  }

  function statusLabel(item) {
    if (item.administered_today) return "Administrado"
    if (item.due_today) return "Pendiente"
    return "Programado"
  }

  function renderAdultSelector(selectedId) {
    const wrapper = document.getElementById("routineAdultSelectorWrapper")
    const selector = document.getElementById("routineAdultSelector")
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

  function renderActivityHistory(items) {
    const container = document.getElementById("activityHistoryList")
    if (!container) return

    const visibleItems = items.slice(0, 2)

    if (!visibleItems.length) {
      container.innerHTML = `
        <div class="activity-history-list">
          <div class="activity-row">
            <div class="activity-icon"></div>
            <div class="activity-name">Sin actividades</div>
            <div class="activity-status">Sin estado</div>
            <div class="activity-note"></div>
          </div>
        </div>
      `
      return
    }

    container.innerHTML = `
      <div class="activity-history-head">
        <span></span>
        <span>Tipo de Actividad</span>
        <span>Estado</span>
        <span>Notas</span>
      </div>
      <div class="activity-history-list">
        ${visibleItems.map((item) => `
          <div class="activity-row">
            <div class="activity-icon"></div>
            <div class="activity-name">${api.escapeHtml(item.medication_name || "Medicamento")}</div>
            <div class="activity-status">${api.escapeHtml(statusLabel(item))}</div>
            <div class="activity-note">${api.escapeHtml(item.notes || "")}</div>
          </div>
        `).join("")}
      </div>
    `
  }

  function renderMedicineTable(items) {
    const body = document.getElementById("routineMedicineTableBody")
    if (!body) return

    if (!items.length) {
      body.innerHTML = `
        <tr>
          <td colspan="3">Sin medicinas programadas</td>
        </tr>
      `
      return
    }

    body.innerHTML = items.map((item) => `
      <tr>
        <td>${api.escapeHtml(item.medication_name || "Medicina")}</td>
        <td>${api.escapeHtml(item.schedule || "Sin horario")}</td>
        <td>${api.escapeHtml(statusLabel(item))}</td>
      </tr>
    `).join("")
  }

  function renderRoutine(data, adult) {
    const items = data.routine || []
    const adultName = adult?.full_name || items[0]?.older_adult_name || "Sin adulto asignado"
    const todayItems = items.filter((item) => item.due_today)
    const visibleItems = todayItems.length ? todayItems : items

    setText("routineResidentName", firstName(adultName))
    setValue("routineAdultName", adultName)
    setValue("routineDateInput", formatInputDate(data.date))
    setValue("routineNotes", visibleItems.find((item) => item.notes)?.notes || "Sin notas registradas para hoy.")

    renderActivityHistory(visibleItems)
    renderMedicineTable(visibleItems)
  }

  function renderError(message) {
    setText("routineResidentName", "Adulto mayor")
    setValue("routineAdultName", "Sin adulto mayor asignado")
    setValue("routineDateInput", "")
    setValue("routineNotes", message)
    renderActivityHistory([])
    renderMedicineTable([])
  }

  async function loadRoutineForAdult(adultId) {
    const adult = assignedAdults.find((item) => String(item.id) === String(adultId))
    const data = await api.fetchJson(`/family/routines?older_adult_id=${encodeURIComponent(adultId)}`)
    renderRoutine(data, adult)
    renderAdultSelector(adultId)
    updateAdultUrl(adultId)
  }

  async function loadRoutine() {
    try {
      const adultsData = await api.fetchJson("/family/older-adults")
      assignedAdults = adultsData.older_adults || []

      if (!assignedAdults.length) {
        renderError("No tienes adultos mayores asignados por ahora.")
        return
      }

      const requestedAdultId = getRequestedAdultId()
      const selectedAdultId = assignedAdults.some((adult) => String(adult.id) === String(requestedAdultId))
        ? requestedAdultId
        : assignedAdults[0].id

      await loadRoutineForAdult(selectedAdultId)
    } catch (error) {
      renderError(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("routineAdultSelector")?.addEventListener("change", (event) => {
      loadRoutineForAdult(event.target.value).catch((error) => renderError(error.message))
    })

    loadRoutine()
  })
})()
