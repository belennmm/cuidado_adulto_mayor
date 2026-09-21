const togglePassword = document.getElementById("togglePassword")
const passwordInput = document.getElementById("password")
const editUserForm = document.getElementById("editUserForm")

const userType = document.getElementById("userType")
const username = document.getElementById("username")
const email = document.getElementById("email")
const locationInput = document.getElementById("location")
const phone = document.getElementById("phone")
const birthdate = document.getElementById("birthdate")
const status = document.getElementById("status")

const userForm = window.AdminUserForm.create({
  form: editUserForm,
  togglePassword,
  fields: { name: username, email, password: passwordInput, role: userType, location: locationInput, phone, birthdate, status },
})

const openDeleteModal = document.getElementById("openDeleteModal")
const closeDeleteModal = document.getElementById("closeDeleteModal")
const confirmDeleteUser = document.getElementById("confirmDeleteUser")
const deleteModal = document.getElementById("deleteModal")

const params = new URLSearchParams(window.location.search)
const userId = params.get("id")

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

function setFormDisabled(disabled) {
  userForm.setDisabled(disabled)
}

function fillForm(user) {
  userForm.fill(user)
}

async function loadUser() {
  if (!userId) {
    await showPopup("No se encontro el usuario a editar.", { variant: "error" })
    navigateTo("./users.html")
    return
  }

  if (!window.CuidadoApi.getToken(["admin"])) {
    await showPopup("Inicia sesión como administrador para editar usuarios.", { variant: "error" })
    navigateTo("../../index.html")
    return
  }

  setFormDisabled(true)

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/users/${userId}`, {
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la solicitud.",
    })

    fillForm(data.user)
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
    navigateTo("./users.html")
  } finally {
    setFormDisabled(false)

    if (userType.value === "admin") {
      status.disabled = true
    }
  }
}

async function saveUser() {
  const payload = userForm.readPayload({ includeApproval: true })

  if (window.CuidadoForms.findMissing(payload, ["name", "email", "role"]).length) {
    await showPopup("Completa nombre, correo y tipo de usuario.", { variant: "error" })
    return
  }

  setFormDisabled(true)

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/users/${userId}`, {
      method: "PUT",
      body: JSON.stringify(payload),
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la solicitud.",
    })

    await showPopup(data.message || "Usuario actualizado correctamente.", { variant: "success" })
    navigateTo("./users.html")
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
  } finally {
    setFormDisabled(false)

    if (userType.value === "admin") {
      status.disabled = true
    }
  }
}

async function deleteUser() {
  setFormDisabled(true)

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/users/${userId}`, {
      method: "DELETE",
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la solicitud.",
    })

    await showPopup(data.message || "Usuario eliminado correctamente.", { variant: "success" })
    navigateTo("./users.html")
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
    setFormDisabled(false)
  }
}

userForm.bindPasswordToggle()
userForm.bindAdminStatus()

if (editUserForm) {
  editUserForm.addEventListener("submit", (event) => {
    event.preventDefault()
    saveUser()
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

if (confirmDeleteUser) {
  confirmDeleteUser.addEventListener("click", () => {
    deleteUser()
  })
}

loadUser()
