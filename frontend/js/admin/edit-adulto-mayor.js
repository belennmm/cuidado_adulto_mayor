const editOlderAdultForm = document.getElementById("editOlderAdultForm")

const fullName = document.getElementById("fullName")
const age = document.getElementById("age")
const birthdate = document.getElementById("birthdate")
const gender = document.getElementById("gender")
const room = document.getElementById("room")
const status = document.getElementById("status")
const caregiverFamily = document.getElementById("caregiverFamily")
const professionalCaregiver = document.getElementById("professionalCaregiver")
const contactName = document.getElementById("contactName")
const contactPhone = document.getElementById("contactPhone")
const allergies = document.getElementById("allergies")
const medicalHistory = document.getElementById("medicalHistory")
const notes = document.getElementById("notes")

const medicinesList = document.getElementById("medicinesList")
const addMedicineButton = document.getElementById("addMedicineButton")

const openDeleteModal = document.getElementById("openDeleteModal")
const closeDeleteModal = document.getElementById("closeDeleteModal")
const confirmDeleteOlderAdult = document.getElementById("confirmDeleteOlderAdult")
const deleteModal = document.getElementById("deleteModal")

const API_URL = "http://127.0.0.1:8080/api"

const params = new URLSearchParams(window.location.search)
const olderAdultId = params.get("id")
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

function getValue(input) {
  return input && typeof input.value === "string" && input.value.trim() !== "" ? input.value.trim() : null
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

function buildPayload() {
  return {
    full_name: getValue(fullName),
    age: getValue(age),
    birthdate: getValue(birthdate),
    gender: getValue(gender),
    room: getValue(room),
    status: getValue(status),
    caregiver_family: getValue(caregiverFamily),
    professional_caregiver_id: getValue(professionalCaregiver),
    emergency_contact_name: getValue(contactName),
    emergency_contact_phone: getValue(contactPhone),
    allergies: getValue(allergies),
    medical_history: getValue(medicalHistory),
    notes: getValue(notes),
    medications: getMedicineCardsPayload(),
  }
}

async function apiRequest(path, options = {}) {
  const token = getToken()

  if (!token) {
    throw new Error("Inicia sesion como administrador para gestionar adultos mayores.")
  }

  const response = await fetch(`${API_URL}${path}`, {
    cache: "no-store",
    ...options,
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(options.headers || {}),
    },
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    const validationErrors = data.errors ? Object.values(data.errors).flat().join("\n") : null
    throw new Error(validationErrors || data.message || "No se pudo completar la operacion.")
  }

  return data
}

function fillForm(olderAdult) {
  fullName.value = olderAdult.full_name || ""
  age.value = olderAdult.age || ""
  birthdate.value = olderAdult.birthdate || ""
  gender.value = olderAdult.gender || ""
  room.value = olderAdult.room || ""
  status.value = olderAdult.status || ""
  caregiverFamily.value = olderAdult.caregiver_family || ""
  if (professionalCaregiver) {
    professionalCaregiver.value = olderAdult.professional_caregiver_id ? String(olderAdult.professional_caregiver_id) : ""
  }
  contactName.value = olderAdult.emergency_contact_name || ""
  contactPhone.value = olderAdult.emergency_contact_phone || ""
  allergies.value = olderAdult.allergies || ""
  medicalHistory.value = olderAdult.medical_history || ""
  notes.value = olderAdult.notes || ""

  if (medicinesList) {
    medicinesList.innerHTML = ""
    medicineCount = 0

    if (Array.isArray(olderAdult.medications) && olderAdult.medications.length) {
      olderAdult.medications.forEach((medicine) => addMedicineCard(medicine))
    } else {
      addMedicineCard()
    }
  }
}

async function loadProfessionalCaregivers(selectedId = null) {
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
      if (selectedId && String(user.id) === String(selectedId)) {
        option.selected = true
      }
      professionalCaregiver.appendChild(option)
    })
  } catch (error) {
    professionalCaregiver.innerHTML = '<option value="">No se pudieron cargar los cuidadores</option>'
  }
}

async function loadOlderAdult() {
  if (!olderAdultId) {
    alert("No se encontro el adulto mayor a editar.")
    window.location.href = "./adultos-mayores.html"
    return
  }

  try {
    const data = await apiRequest(`/admin/older-adults/${olderAdultId}`)
    await loadProfessionalCaregivers(data.older_adult?.professional_caregiver_id)
    fillForm(data.older_adult || {})
  } catch (error) {
    alert(error.message)
    window.location.href = "./adultos-mayores.html"
  }
}

async function updateOlderAdult() {
  const payload = buildPayload()

  if (!payload.full_name) {
    throw new Error("Ingresa el nombre completo del adulto mayor.")
  }

  return apiRequest(`/admin/older-adults/${olderAdultId}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  })
}

async function deleteOlderAdult() {
  return apiRequest(`/admin/older-adults/${olderAdultId}`, {
    method: "DELETE",
  })
}

if (addMedicineButton) {
  addMedicineButton.addEventListener("click", () => addMedicineCard())
}

if (medicinesList) {
  addMedicineCard()
}

if (!olderAdultId) {
  loadProfessionalCaregivers()
}

if (editOlderAdultForm) {
  editOlderAdultForm.addEventListener("submit", async (event) => {
    event.preventDefault()

    const submitButton = editOlderAdultForm.querySelector(".primary-button")

    try {
      if (submitButton) {
        submitButton.disabled = true
        submitButton.textContent = "Guardando..."
      }

      const data = await updateOlderAdult()
      alert(data.message || "Se guardaron los cambios del adulto mayor.")
      window.location.href = "./adultos-mayores.html"
    } catch (error) {
      alert(error.message)
    } finally {
      if (submitButton) {
        submitButton.disabled = false
        submitButton.textContent = "Guardar cambios"
      }
    }
  })
}

if (openDeleteModal && deleteModal) {
  openDeleteModal.addEventListener("click", () => {
    deleteModal.classList.add("active")
  })
}

if (closeDeleteModal && deleteModal) {
  closeDeleteModal.addEventListener("click", () => {
    deleteModal.classList.remove("active")
  })
}

if (deleteModal) {
  deleteModal.addEventListener("click", (event) => {
    if (event.target === deleteModal) {
      deleteModal.classList.remove("active")
    }
  })
}

if (confirmDeleteOlderAdult) {
  confirmDeleteOlderAdult.addEventListener("click", async () => {
    try {
      confirmDeleteOlderAdult.disabled = true
      confirmDeleteOlderAdult.textContent = "Eliminando..."

      const data = await deleteOlderAdult()
      alert(data.message || "Adulto mayor eliminado correctamente.")
      window.location.href = "./adultos-mayores.html"
    } catch (error) {
      alert(error.message)
      confirmDeleteOlderAdult.disabled = false
      confirmDeleteOlderAdult.textContent = "Si, eliminar"
    }
  })
}

loadOlderAdult()
