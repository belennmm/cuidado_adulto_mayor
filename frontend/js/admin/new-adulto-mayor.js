const newOlderAdultForm = document.getElementById("newOlderAdultForm")
const medicinesList = document.getElementById("medicinesList")
const addMedicineButton = document.getElementById("addMedicineButton")
const caregiverFamily = document.getElementById("caregiverFamily")
const professionalCaregiver = document.getElementById("professionalCaregiver")

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

function buildPayload(formData) {
  return {
    ...window.CuidadoForms.readPayload({
      full_name: { formData, key: "fullName" },
      age: { formData, key: "age" },
      birthdate: { formData, key: "birthdate" },
      gender: { formData, key: "gender" },
      room: { formData, key: "room" },
      status: { formData, key: "status" },
      family_caregiver_id: { formData, key: "caregiverFamily" },
      professional_caregiver_id: { formData, key: "professionalCaregiver" },
      emergency_contact_name: { formData, key: "contactName" },
      emergency_contact_phone: { formData, key: "contactPhone" },
      allergies: { formData, key: "allergies" },
      medical_history: { formData, key: "medicalHistory" },
      notes: { formData, key: "notes" },
    }),
    medications: medicineManager.read(),
  }
}

async function loadFamilyCaregivers() {
  return window.OlderAdultForm.loadCaregiverOptions(caregiverFamily, {
    path: "/admin/family-caregivers",
    placeholder: "Seleccione cuidador familiar",
  })
}

async function loadProfessionalCaregivers() {
  return window.OlderAdultForm.loadCaregiverOptions(professionalCaregiver, {
    path: "/admin/professional-caregivers",
    placeholder: "Seleccione cuidador profesional",
  })
}

async function createOlderAdult(payload) {
  if (!window.CuidadoApi.getToken(["admin"])) {
    throw new Error("Inicia sesión como administrador para crear adultos mayores.")
  }

  return window.CuidadoApi.fetchJson("/admin/older-adults", {
    method: "POST",
    body: JSON.stringify(payload),
    expectedRoles: ["admin"],
    fallbackError: "No se pudo crear el adulto mayor.",
  })
}

if (newOlderAdultForm) {
  newOlderAdultForm.addEventListener("submit", async (event) => {
    event.preventDefault()

    const submitButton = newOlderAdultForm.querySelector(".primary-button")
    const formData = new FormData(newOlderAdultForm)
    const payload = buildPayload(formData)

    if (window.CuidadoForms.findMissing(payload, ["full_name"]).length) {
      await showPopup("Ingresa el nombre completo del adulto mayor.", { variant: "error" })
      return
    }

    try {
      if (submitButton) {
        submitButton.disabled = true
        submitButton.textContent = "Creando..."
      }

      const data = await createOlderAdult(payload)
      await showPopup(data.message || "Adulto mayor creado correctamente.", { variant: "success" })
      navigateTo("./adultos-mayores.html")
    } catch (error) {
      await showPopup(error.message, { variant: "error" })
    } finally {
      if (submitButton) {
        submitButton.disabled = false
        submitButton.textContent = "Crear adulto mayor"
      }
    }
  })
}

addMedicineButton?.addEventListener("click", () => medicineManager.add())

if (medicinesList) {
  medicineManager.add()
}

loadFamilyCaregivers()
loadProfessionalCaregivers()
