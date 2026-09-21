const newOlderAdultForm = document.getElementById("newOlderAdultForm")
const medicinesList = document.getElementById("medicinesList")
const addMedicineButton = document.getElementById("addMedicineButton")
const olderAdultForm = window.OlderAdultForm.create({ form: newOlderAdultForm, medicinesList })
const medicineManager = olderAdultForm.medicines

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
    const payload = olderAdultForm.buildPayload()

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

olderAdultForm.loadCaregivers()
