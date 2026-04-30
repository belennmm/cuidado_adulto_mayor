(() => {
  const api = window.FamilyCare
  let selectedAdult = null

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
    setValue("adultNotesField", adult.notes || "Sin notas registradas.")
    setValue("adultEmergencyField", adult.emergency_contact_phone || "Sin numero registrado")
    setText("adultHistorySummary", adult.medical_history ? "Historial registrado" : "Sin historial registrado")
    setText("adultHistoryText", adult.medical_history || "No hay historial medico registrado.")
    renderMedicines(adult.medications || [])
  }

  function renderEmpty(message) {
    setText("adultScreenName", "Adulto mayor")
    setValue("adultNameField", "")
    setValue("adultAgeField", "")
    setValue("adultNotesField", message)
    setValue("adultEmergencyField", "")
    setText("adultHistorySummary", "Sin informacion")
    setText("adultHistoryText", message)
    renderMedicines([])
  }

  async function loadOlderAdults() {
    try {
      const data = await api.fetchJson("/family/older-adults")
      const adults = data.older_adults || []

      if (!adults.length) {
        renderEmpty("No tienes adultos mayores asignados por ahora.")
        return
      }

      renderAdult(adults[0])
    } catch (error) {
      renderEmpty(error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("requestChangeButton")?.addEventListener("click", () => {
      const name = selectedAdult?.full_name || "este adulto mayor"
      alert(`Solicitud registrada para revisar los datos de ${name}.`)
    })

    loadOlderAdults()
  })
})()
