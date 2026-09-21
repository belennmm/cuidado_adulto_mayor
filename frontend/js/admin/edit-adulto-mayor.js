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

const params = new URLSearchParams(window.location.search)
const olderAdultId = params.get("id")
const medicineManager = window.OlderAdultForm.createMedicineManager(medicinesList)

function navigateTo(url) {
  if (window.navigateWithLoading) {
    window.navigateWithLoading(url)
    return
  }

  window.location.assign(url)
}

async function showPopup(message, options = {}) {
  if (window.showAdminAlert) {
    await window.showAdminAlert(message, options)
    return
  }

  console.warn(message)
}

function buildPayload() {
  return {
    ...window.CuidadoForms.readPayload({
      full_name: fullName,
      age,
      birthdate,
      gender,
      room,
      status,
      family_caregiver_id: caregiverFamily,
      professional_caregiver_id: professionalCaregiver,
      emergency_contact_name: contactName,
      emergency_contact_phone: contactPhone,
      allergies,
      medical_history: medicalHistory,
      notes,
    }),
    medications: medicineManager.read({ includeIds: true }),
  }
}

function fillForm(olderAdult) {
  fullName.value = olderAdult.full_name || ""
  age.value = olderAdult.age || ""
  birthdate.value = olderAdult.birthdate || ""
  gender.value = olderAdult.gender || ""
  room.value = olderAdult.room || ""
  status.value = olderAdult.status || ""
  caregiverFamily.value = olderAdult.family_caregiver_id ? String(olderAdult.family_caregiver_id) : ""
  if (professionalCaregiver) {
    professionalCaregiver.value = olderAdult.professional_caregiver_id ? String(olderAdult.professional_caregiver_id) : ""
  }
  contactName.value = olderAdult.emergency_contact_name || ""
  contactPhone.value = olderAdult.emergency_contact_phone || ""
  allergies.value = olderAdult.allergies || ""
  medicalHistory.value = olderAdult.medical_history || ""
  notes.value = olderAdult.notes || ""

  medicineManager.fill(Array.isArray(olderAdult.medications) ? olderAdult.medications : [])
}

async function loadFamilyCaregivers(selectedId = null) {
  return window.OlderAdultForm.loadCaregiverOptions(caregiverFamily, {
    path: "/admin/family-caregivers",
    placeholder: "Seleccione cuidador familiar",
    selectedId,
  })
}

async function loadProfessionalCaregivers(selectedId = null) {
  return window.OlderAdultForm.loadCaregiverOptions(professionalCaregiver, {
    path: "/admin/professional-caregivers",
    placeholder: "Seleccione cuidador profesional",
    selectedId,
  })
}

async function loadOlderAdult() {
  if (!olderAdultId) {
    await showPopup("No se encontro el adulto mayor a editar.", { variant: "error" })
    navigateTo("./adultos-mayores.html")
    return
  }

  try {
    if (!window.CuidadoApi.getToken(["admin"])) {
      throw new Error("Inicia sesión como administrador para gestionar adultos mayores.")
    }

    const data = await window.CuidadoApi.fetchJson(`/admin/older-adults/${olderAdultId}`, {
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la operacion.",
    })
    await Promise.all([
      loadFamilyCaregivers(data.older_adult?.family_caregiver_id),
      loadProfessionalCaregivers(data.older_adult?.professional_caregiver_id),
    ])
    fillForm(data.older_adult || {})
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
    navigateTo("./adultos-mayores.html")
  }
}

async function updateOlderAdult() {
  const payload = buildPayload()

  if (window.CuidadoForms.findMissing(payload, ["full_name"]).length) {
    throw new Error("Ingresa el nombre completo del adulto mayor.")
  }

  if (!window.CuidadoApi.getToken(["admin"])) {
    throw new Error("Inicia sesión como administrador para gestionar adultos mayores.")
  }

  return window.CuidadoApi.fetchJson(`/admin/older-adults/${olderAdultId}`, {
    method: "PUT",
    body: JSON.stringify(payload),
    expectedRoles: ["admin"],
    fallbackError: "No se pudo completar la operacion.",
  })
}

async function deleteOlderAdult() {
  if (!window.CuidadoApi.getToken(["admin"])) {
    throw new Error("Inicia sesión como administrador para gestionar adultos mayores.")
  }

  return window.CuidadoApi.fetchJson(`/admin/older-adults/${olderAdultId}`, {
    method: "DELETE",
    expectedRoles: ["admin"],
    fallbackError: "No se pudo completar la operacion.",
  })
}

addMedicineButton?.addEventListener("click", () => medicineManager.add())

if (medicinesList) {
  medicineManager.add()
}

if (!olderAdultId) {
  loadFamilyCaregivers()
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
      await showPopup(data.message || "Se guardaron los cambios del adulto mayor.", { variant: "success" })
      navigateTo("./adultos-mayores.html")
    } catch (error) {
      await showPopup(error.message, { variant: "error" })
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
      await showPopup(data.message || "Adulto mayor eliminado correctamente.", { variant: "success" })
      navigateTo("./adultos-mayores.html")
    } catch (error) {
      await showPopup(error.message, { variant: "error" })
      confirmDeleteOlderAdult.disabled = false
      confirmDeleteOlderAdult.textContent = "Si, eliminar"
    }
  })
}

loadOlderAdult()
