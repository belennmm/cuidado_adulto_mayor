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
    function add(medicine = null) {
      if (!list) return null
      count += 1
      const el = window.CuidadoUi.element
      const card = el("div", "medicine-card")
      card.dataset.index = String(count)
      card.dataset.medicationAssignmentId = String(medicine?.id || "")
      const remove = el("button", "remove-medicine-button danger-soft-button", "Eliminar")
      remove.type = "button"
      remove.addEventListener("click", () => card.remove())
      const grid = el("div", "medicine-grid")
      for (const [key, label, placeholder, multiline] of [
        ["Name", "Nombre de medicina", "Ingrese nombre de medicina", false],
        ["Dosage", "Dosis", "Ej. 1 pastilla", false],
        ["Schedule", "Horario", "Ej. 8:00 AM, 2:00 PM", false],
        ["Notes", "Notas", "Indicaciones adicionales", true],
      ]) {
        const input = document.createElement(multiline ? "textarea" : "input")
        if (!multiline) input.type = "text"
        input.id = `medicine${key}${count}`
        input.name = input.id
        input.placeholder = placeholder
        input.value = String(medicine?.[key === "Name" ? "name" : key.toLowerCase()] || "")
        const title = el("label", "", label)
        title.htmlFor = input.id
        grid.append(el("div", `form-group${multiline ? " full-width" : ""}`, null, [title, input]))
      }
      const options = DAYS.map(([value, label]) => {
        const input = document.createElement("input")
        input.type = "checkbox"
        input.name = `medicineDays${count}`
        input.value = value
        input.checked = Array.isArray(medicine?.days) && medicine.days.includes(value)
        return el("label", "day-option", null, [input, el("span", "", label)])
      })
      card.append(el("div", "medicine-card-header", null, [el("h3", "medicine-card-title", `Medicina ${count}`), remove]), grid,
        el("div", "days-group", null, [el("label", "", "Días de administración"), el("div", "days-options", null, options)]))
      list.append(card)
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
      list.replaceChildren()
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
