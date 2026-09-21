const editOlderAdultForm = document.getElementById("editOlderAdultForm")

const medicinesList = document.getElementById("medicinesList")
const addMedicineButton = document.getElementById("addMedicineButton")

const openDeleteModal = document.getElementById("openDeleteModal")
const closeDeleteModal = document.getElementById("closeDeleteModal")
const confirmDeleteOlderAdult = document.getElementById("confirmDeleteOlderAdult")
const deleteModal = document.getElementById("deleteModal")

const params = new URLSearchParams(window.location.search)
const olderAdultId = params.get("id")
const olderAdultForm = window.OlderAdultForm.create({ form: editOlderAdultForm, medicinesList, includeMedicationIds: true })
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
    await olderAdultForm.loadCaregivers({
      familySelectedId: data.older_adult?.family_caregiver_id,
      professionalSelectedId: data.older_adult?.professional_caregiver_id,
    })
    olderAdultForm.fill(data.older_adult || {})
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
    navigateTo("./adultos-mayores.html")
  }
}

async function updateOlderAdult() {
  const payload = olderAdultForm.buildPayload()

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
  olderAdultForm.loadCaregivers()
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
