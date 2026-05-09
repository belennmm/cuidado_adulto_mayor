(() => {
  const api = window.FamilyCare
  let selectedAdult = null
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

  function formatEmergency(adult) {
    const name = adult.emergency_contact_name || ""
    const phone = adult.emergency_contact_phone || ""

    if (name && phone) return `${name} - ${phone}`
    return phone || name || "Sin contacto registrado"
  }

  function renderAdultSelector(selectedId) {
    const wrapper = document.getElementById("adultSelectorWrapper")
    const selector = document.getElementById("adultSelector")
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

  function renderMedicines(medications) {
    const list = document.getElementById("adultMedicineList")
    const summary = document.getElementById("adultMedicineSummary")
    if (!list) return

    if (!medications?.length) {
      if (summary) summary.textContent = "Sin medicamentos activos"
      list.innerHTML = "No hay medicinas activas registradas."
      return
    }

    if (summary) summary.textContent = `${medications.length} medicinas activas`

    list.innerHTML = medications
      .map((medicine) => `
        <div class="medicine-summary-item">
          <strong>${api.escapeHtml(medicine.name || "Medicina")}</strong>
          <span>${api.escapeHtml(medicine.schedule || "Sin horario")}</span>
          <span>${api.escapeHtml(medicine.dosage || "Sin dosis")}</span>
          <span>${medicine.due_today ? "Hoy" : "Programada"}</span>
        </div>
      `)
      .join("")
  }

  function renderAdult(adult) {
    selectedAdult = adult

    setText("adultScreenName", firstName(adult.full_name))
    setValue("adultNameField", adult.full_name)
    setValue("adultAgeField", adult.age ? `${adult.age} anios` : "Sin edad registrada")
    setValue("adultStatusField", adult.status || "Sin estado registrado")
    setValue("adultRoomField", adult.room || "Sin habitacion registrada")
    setValue("adultNotesField", adult.notes || "Sin notas registradas.")
    setValue("adultEmergencyField", formatEmergency(adult))
    setText("adultHistorySummary", adult.medical_history ? "Historial registrado" : "Sin historial registrado")
    setText(
      "adultHistoryText",
      [
        adult.medical_history || "No hay historial medico registrado.",
        adult.allergies ? `Alergias: ${adult.allergies}` : "",
        adult.professional_caregiver_name ? `Cuidador profesional: ${adult.professional_caregiver_name}` : "",
      ].filter(Boolean).join("\n")
    )
    renderMedicines(adult.medications || [])
    renderAdultSelector(adult.id)
    updateAdultUrl(adult.id)
  }

  function renderEmpty(message) {
    setText("adultScreenName", "Adulto mayor")
    setValue("adultNameField", "")
    setValue("adultAgeField", "")
    setValue("adultStatusField", "")
    setValue("adultRoomField", "")
    setValue("adultNotesField", message)
    setValue("adultEmergencyField", "")
    setText("adultHistorySummary", "Sin informacion")
    setText("adultHistoryText", message)
    renderMedicines([])
  }

  async function loadAdultDetail(adultId) {
    const data = await api.fetchJson(`/family/older-adults/${encodeURIComponent(adultId)}`)
    renderAdult(data.older_adult || {})
  }

  async function loadOlderAdults() {
    try {
      const data = await api.fetchJson("/family/older-adults")
      assignedAdults = data.older_adults || []

      if (!assignedAdults.length) {
        renderEmpty("No tienes adultos mayores asignados por ahora.")
        return
      }

      const requestedAdultId = getRequestedAdultId()
      const selectedAdultId = requestedAdultId || assignedAdults[0].id

      renderAdultSelector(selectedAdultId)
      await loadAdultDetail(selectedAdultId)
    } catch (error) {
      renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("adultSelector")?.addEventListener("change", (event) => {
      loadAdultDetail(event.target.value).catch((error) => renderEmpty(error.message))
    })

    document.getElementById("requestChangeButton")?.addEventListener("click", async () => {
      const name = selectedAdult?.full_name || "este adulto mayor"
      await api.showAlert(`Solicitud registrada para revisar los datos de ${name}.`, {
        title: "Solicitud registrada",
        variant: "info",
      })
    })

    loadOlderAdults()
  })
})()
