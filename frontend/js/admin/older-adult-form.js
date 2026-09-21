(() => {
  const DAYS = [
    ["lunes", "Lunes"],
    ["martes", "Martes"],
    ["miercoles", "Miercoles"],
    ["jueves", "Jueves"],
    ["viernes", "Viernes"],
    ["sabado", "Sabado"],
    ["domingo", "Domingo"],
  ]

  const FIELD_NAMES = Object.freeze({
    full_name: "fullName",
    age: "age",
    birthdate: "birthdate",
    gender: "gender",
    room: "room",
    status: "status",
    family_caregiver_id: "caregiverFamily",
    professional_caregiver_id: "professionalCaregiver",
    emergency_contact_name: "contactName",
    emergency_contact_phone: "contactPhone",
    allergies: "allergies",
    medical_history: "medicalHistory",
    notes: "notes",
  })

  function createMedicineManager(list) {
    let count = 0
    const escapeHtml = window.CuidadoUi.escapeHtml

    function dayOptions(index, selectedDays = []) {
      return DAYS.map(([value, label]) => `
        <label class="day-option">
          <input type="checkbox" name="medicineDays${index}" value="${value}" ${selectedDays.includes(value) ? "checked" : ""} />
          <span>${label}</span>
        </label>
      `).join("")
    }

    function add(medicine = null) {
      if (!list) return null
      count += 1

      const card = document.createElement("div")
      card.className = "medicine-card"
      card.dataset.index = count
      card.dataset.medicationAssignmentId = medicine?.id || ""
      card.innerHTML = `
        <div class="medicine-card-header">
          <h3 class="medicine-card-title">Medicina ${count}</h3>
          <button type="button" class="remove-medicine-button danger-soft-button">Eliminar</button>
        </div>
        <div class="medicine-grid">
          <div class="form-group">
            <label for="medicineName${count}">Nombre de medicina</label>
            <input type="text" id="medicineName${count}" name="medicineName${count}" placeholder="Ingrese nombre de medicina" value="${escapeHtml(medicine?.name || "")}" />
          </div>
          <div class="form-group">
            <label for="medicineDosage${count}">Dosis</label>
            <input type="text" id="medicineDosage${count}" name="medicineDosage${count}" placeholder="Ej. 1 pastilla" value="${escapeHtml(medicine?.dosage || "")}" />
          </div>
          <div class="form-group">
            <label for="medicineSchedule${count}">Horario</label>
            <input type="text" id="medicineSchedule${count}" name="medicineSchedule${count}" placeholder="Ej. 8:00 AM, 2:00 PM" value="${escapeHtml(medicine?.schedule || "")}" />
          </div>
          <div class="form-group full-width">
            <label for="medicineNotes${count}">Notas</label>
            <textarea id="medicineNotes${count}" name="medicineNotes${count}" placeholder="Indicaciones adicionales">${escapeHtml(medicine?.notes || "")}</textarea>
          </div>
        </div>
        <div class="days-group">
          <label>Días de administración</label>
          <div class="days-options">${dayOptions(count, medicine?.days || [])}</div>
        </div>
      `
      card.querySelector(".remove-medicine-button")?.addEventListener("click", () => card.remove())
      list.appendChild(card)
      return card
    }

    function read({ includeIds = false } = {}) {
      return Array.from(list?.querySelectorAll(".medicine-card") || []).map((card) => {
        const index = card.dataset.index
        const name = card.querySelector(`#medicineName${index}`)?.value.trim() || ""
        if (!name) return null

        const medicine = {
          name,
          dosage: card.querySelector(`#medicineDosage${index}`)?.value.trim() || null,
          schedule: card.querySelector(`#medicineSchedule${index}`)?.value.trim() || null,
          days: Array.from(card.querySelectorAll(`input[name="medicineDays${index}"]:checked`)).map((input) => input.value),
          notes: card.querySelector(`#medicineNotes${index}`)?.value.trim() || null,
        }
        if (includeIds) {
          medicine.id = card.dataset.medicationAssignmentId
            ? Number(card.dataset.medicationAssignmentId)
            : null
        }
        return medicine
      }).filter(Boolean)
    }

    function fill(medicines = []) {
      if (!list) return
      list.innerHTML = ""
      count = 0
      medicines.length ? medicines.forEach(add) : add()
    }

    return Object.freeze({ add, fill, read })
  }

  async function loadCaregiverOptions(select, { path, placeholder, selectedId = null }) {
    if (!select || !window.CuidadoApi.getToken(["admin"])) return

    try {
      const data = await window.CuidadoApi.fetchJson(path, {
        expectedRoles: ["admin"],
        fallbackError: "No se pudieron cargar los cuidadores.",
      })
      select.replaceChildren(new Option(placeholder, ""))
      ;(data.users || []).forEach((user) => {
        const option = new Option(user.name, String(user.id))
        option.selected = selectedId !== null && String(user.id) === String(selectedId)
        select.appendChild(option)
      })
    } catch (error) {
      select.replaceChildren(new Option("No se pudieron cargar los cuidadores", ""))
    }
  }

  function create({ form, medicinesList, includeMedicationIds = false }) {
    const medicines = createMedicineManager(medicinesList)

    function getControl(name) {
      return form?.elements?.namedItem(name) || null
    }

    function buildPayload() {
      const formData = new FormData(form)
      const fields = Object.fromEntries(
        Object.entries(FIELD_NAMES).map(([payloadName, formName]) => [
          payloadName,
          { formData, key: formName },
        ])
      )

      return {
        ...window.CuidadoForms.readPayload(fields),
        medications: medicines.read({ includeIds: includeMedicationIds }),
      }
    }

    function fill(olderAdult = {}) {
      Object.entries(FIELD_NAMES).forEach(([payloadName, formName]) => {
        const control = getControl(formName)
        if (!control) return

        let value = olderAdult[payloadName]
        if (payloadName === "birthdate" && value) value = String(value).slice(0, 10)
        if (payloadName.endsWith("_caregiver_id") && value) value = String(value)
        control.value = value ?? ""
      })
      medicines.fill(Array.isArray(olderAdult.medications) ? olderAdult.medications : [])
    }

    async function loadCaregivers({ familySelectedId = null, professionalSelectedId = null } = {}) {
      return Promise.all([
        loadCaregiverOptions(getControl("caregiverFamily"), {
          path: "/admin/family-caregivers",
          placeholder: "Seleccione cuidador familiar",
          selectedId: familySelectedId,
        }),
        loadCaregiverOptions(getControl("professionalCaregiver"), {
          path: "/admin/professional-caregivers",
          placeholder: "Seleccione cuidador profesional",
          selectedId: professionalSelectedId,
        }),
      ])
    }

    return Object.freeze({ buildPayload, fill, loadCaregivers, medicines })
  }

  window.OlderAdultForm = Object.freeze({ create, createMedicineManager, loadCaregiverOptions })
})()
