const newOlderAdultForm = document.getElementById("newOlderAdultForm")
const medicinesList = document.getElementById("medicinesList")
const addMedicineButton = document.getElementById("addMedicineButton")
const professionalCaregiver = document.getElementById("professionalCaregiver")

const API_URL = "http://127.0.0.1:8080/api"
let medicineCount = 0

function createDayOptions(index, selectedDays = []) {
  const days = [
    { value: "lunes", label: "Lunes" },
    { value: "martes", label: "Martes" },
    { value: "miercoles", label: "Miercoles" },
    { value: "jueves", label: "Jueves" },
    { value: "viernes", label: "Viernes" },
    { value: "sabado", label: "Sabado" },
    { value: "domingo", label: "Domingo" },
  ]

  return days
    .map(
      (day) => `
        <label class="day-option">
          <input type="checkbox" name="medicineDays${index}" value="${day.value}" ${selectedDays.includes(day.value) ? "checked" : ""} />
          <span>${day.label}</span>
        </label>
      `
    )
    .join("")
}

function addMedicineCard(medicine = null) {
  if (!medicinesList) return

  medicineCount += 1

  const card = document.createElement("div")
  card.className = "medicine-card"
  card.dataset.index = medicineCount

  card.innerHTML = `
    <div class="medicine-card-header">
      <h3 class="medicine-card-title">Medicina ${medicineCount}</h3>
      <button type="button" class="remove-medicine-button">Eliminar</button>
    </div>

    <div class="medicine-grid">
      <div class="form-group">
        <label for="medicineName${medicineCount}">Nombre de medicina</label>
        <input
          type="text"
          id="medicineName${medicineCount}"
          name="medicineName${medicineCount}"
          placeholder="Ingrese nombre de medicina"
          value="${medicine?.name || ""}"
        />
      </div>

      <div class="form-group">
        <label for="medicineDosage${medicineCount}">Dosis</label>
        <input
          type="text"
          id="medicineDosage${medicineCount}"
          name="medicineDosage${medicineCount}"
          placeholder="Ej. 1 pastilla"
          value="${medicine?.dosage || ""}"
        />
      </div>

      <div class="form-group">
        <label for="medicineSchedule${medicineCount}">Horario</label>
        <input
          type="text"
          id="medicineSchedule${medicineCount}"
          name="medicineSchedule${medicineCount}"
          placeholder="Ej. 8:00 AM, 2:00 PM"
          value="${medicine?.schedule || ""}"
        />
      </div>

      <div class="form-group full-width">
        <label for="medicineNotes${medicineCount}">Notas</label>
        <textarea
          id="medicineNotes${medicineCount}"
          name="medicineNotes${medicineCount}"
          placeholder="Indicaciones adicionales"
        >${medicine?.notes || ""}</textarea>
      </div>
    </div>

    <div class="days-group">
      <label>Dias de administracion</label>
      <div class="days-options">
        ${createDayOptions(medicineCount, medicine?.days || [])}
      </div>
    </div>
  `

  const removeButton = card.querySelector(".remove-medicine-button")
  removeButton.addEventListener("click", () => {
    card.remove()
  })

  medicinesList.appendChild(card)
}

function getToken() {
  return localStorage.getItem("token")
}

function getValue(formData, key) {
  const value = formData.get(key)
  return typeof value === "string" && value.trim() !== "" ? value.trim() : null
}

function getMedicineCardsPayload() {
  return Array.from(document.querySelectorAll(".medicine-card"))
    .map((card) => {
      const index = card.dataset.index
      const name = document.getElementById(`medicineName${index}`)?.value.trim() || ""
      const dosage = document.getElementById(`medicineDosage${index}`)?.value.trim() || ""
      const schedule = document.getElementById(`medicineSchedule${index}`)?.value.trim() || ""
      const notes = document.getElementById(`medicineNotes${index}`)?.value.trim() || ""
      const days = Array.from(card.querySelectorAll(`input[name="medicineDays${index}"]:checked`)).map((input) => input.value)

      if (!name) {
        return null
      }

      return {
        name,
        dosage: dosage || null,
        schedule: schedule || null,
        days,
        notes: notes || null,
      }
    })
    .filter(Boolean)
}

function buildPayload(formData) {
  return {
    full_name: getValue(formData, "fullName"),
    age: getValue(formData, "age"),
    birthdate: getValue(formData, "birthdate"),
    gender: getValue(formData, "gender"),
    room: getValue(formData, "room"),
    status: getValue(formData, "status"),
    caregiver_family: getValue(formData, "caregiverFamily"),
    professional_caregiver_id: getValue(formData, "professionalCaregiver"),
    emergency_contact_name: getValue(formData, "contactName"),
    emergency_contact_phone: getValue(formData, "contactPhone"),
    allergies: getValue(formData, "allergies"),
    medical_history: getValue(formData, "medicalHistory"),
    notes: getValue(formData, "notes"),
    medications: getMedicineCardsPayload(),
  }
}

async function loadProfessionalCaregivers() {
  if (!professionalCaregiver) return

  const token = getToken()

  if (!token) return

  try {
    const response = await fetch(`${API_URL}/admin/professional-caregivers`, {
      cache: "no-store",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || "No se pudieron cargar los cuidadores profesionales.")
    }

    professionalCaregiver.innerHTML = '<option value="">Seleccione cuidador profesional</option>'

    ;(data.users || []).forEach((user) => {
      const option = document.createElement("option")
      option.value = String(user.id)
      option.textContent = user.name
      professionalCaregiver.appendChild(option)
    })
  } catch (error) {
    professionalCaregiver.innerHTML = '<option value="">No se pudieron cargar los cuidadores</option>'
  }
}

async function createOlderAdult(payload) {
  const token = getToken()

  if (!token) {
    throw new Error("Inicia sesion como administrador para crear adultos mayores.")
  }

  const response = await fetch(`${API_URL}/admin/older-adults`, {
    method: "POST",
    cache: "no-store",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "Authorization": `Bearer ${token}`,
    },
    body: JSON.stringify(payload),
  })

  const data = await response.json()

  if (!response.ok) {
    const validationErrors = data.errors ? Object.values(data.errors).flat().join("\n") : null
    throw new Error(validationErrors || data.message || "No se pudo crear el adulto mayor.")
  }

  return data
}

if (newOlderAdultForm) {
  newOlderAdultForm.addEventListener("submit", async (event) => {
    event.preventDefault()

    const submitButton = newOlderAdultForm.querySelector(".primary-button")
    const formData = new FormData(newOlderAdultForm)
    const payload = buildPayload(formData)

    if (!payload.full_name) {
      alert("Ingresa el nombre completo del adulto mayor.")
      return
    }

    try {
      if (submitButton) {
        submitButton.disabled = true
        submitButton.textContent = "Creando..."
      }

      const data = await createOlderAdult(payload)
      alert(data.message || "Adulto mayor creado correctamente.")
      window.location.href = "./adultos-mayores.html"
    } catch (error) {
      alert(error.message)
    } finally {
      if (submitButton) {
        submitButton.disabled = false
        submitButton.textContent = "Crear adulto mayor"
      }
    }
  })
}

if (addMedicineButton) {
  addMedicineButton.addEventListener("click", () => addMedicineCard())
}

if (medicinesList) {
  addMedicineCard()
}

loadProfessionalCaregivers()
