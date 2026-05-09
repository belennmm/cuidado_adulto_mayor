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

const openDeleteModal = document.getElementById("openDeleteModal")
const closeDeleteModal = document.getElementById("closeDeleteModal")
const confirmDeleteUser = document.getElementById("confirmDeleteUser")
const deleteModal = document.getElementById("deleteModal")

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`
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

  alert(message)
}

function getToken() {
  return localStorage.getItem("token")
}

function isApproved(value) {
  return value === true || value === 1 || value === "1" || value === "true" || value === "t"
}

function getErrorMessage(data, fallback) {
  if (data?.message) return data.message

  if (data?.errors) {
    const firstError = Object.values(data.errors).flat()[0]
    if (firstError) return firstError
  }

  return fallback
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, options)
  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(getErrorMessage(data, "No se pudo completar la solicitud."))
  }

  return data
}

function getAuthHeaders(includeJson = false) {
  const headers = {
    "Accept": "application/json",
    "Authorization": `Bearer ${getToken()}`
  }

  if (includeJson) {
    headers["Content-Type"] = "application/json"
  }

  return headers
}

function setFormDisabled(disabled) {
  editUserForm?.querySelectorAll("input, select, button").forEach((element) => {
    element.disabled = disabled
  })
}

function formatDate(value) {
  if (!value) return ""
  return String(value).slice(0, 10)
}

function fillForm(user) {
  userType.value = user.role || ""
  username.value = user.name || ""
  email.value = user.email || ""
  locationInput.value = user.location || ""
  phone.value = user.phone || ""
  birthdate.value = formatDate(user.birthdate)
  status.value = user.role === "admin" || isApproved(user.is_approved) ? "Activo" : "Pendiente"
  passwordInput.value = ""

  if (user.role === "admin") {
    status.value = "Activo"
    status.disabled = true
  }
}

async function loadUser() {
  if (!userId) {
    await showPopup("No se encontro el usuario a editar.", { variant: "error" })
    navigateTo("./users.html")
    return
  }

  if (!getToken()) {
    await showPopup("Inicia sesion como administrador para editar usuarios.", { variant: "error" })
    navigateTo("../../index.html")
    return
  }

  setFormDisabled(true)

  try {
    const data = await fetchJson(`${API_URL}/admin/users/${userId}`, {
      headers: getAuthHeaders()
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
  const payload = {
    name: username.value.trim(),
    email: email.value.trim(),
    role: userType.value,
    is_approved: status.value === "Activo",
    location: locationInput.value.trim() || null,
    phone: phone.value.trim() || null,
    birthdate: birthdate.value || null
  }

  const password = passwordInput.value.trim()

  if (password) {
    payload.password = password
  }

  if (!payload.name || !payload.email || !payload.role) {
    await showPopup("Completa nombre, correo y tipo de usuario.", { variant: "error" })
    return
  }

  setFormDisabled(true)

  try {
    const data = await fetchJson(`${API_URL}/admin/users/${userId}`, {
      method: "PUT",
      headers: getAuthHeaders(true),
      body: JSON.stringify(payload)
    })

    await showPopup(data.message || "Usuario actualizado correctamente.")
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
    const data = await fetchJson(`${API_URL}/admin/users/${userId}`, {
      method: "DELETE",
      headers: getAuthHeaders()
    })

    await showPopup(data.message || "Usuario eliminado correctamente.")
    navigateTo("./users.html")
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
    setFormDisabled(false)
  }
}

if (togglePassword && passwordInput) {
  togglePassword.addEventListener("click", () => {
    const isPassword = passwordInput.type === "password"
    passwordInput.type = isPassword ? "text" : "password"

    const icon = togglePassword.querySelector("i")
    if (icon) {
      icon.classList.toggle("bx-hide")
      icon.classList.toggle("bx-show")
    }
  })
}

if (userType && status) {
  userType.addEventListener("change", () => {
    const isAdmin = userType.value === "admin"
    status.disabled = isAdmin

    if (isAdmin) {
      status.value = "Activo"
    }
  })
}

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
