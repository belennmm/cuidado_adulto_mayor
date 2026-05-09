const togglePassword = document.getElementById("togglePassword")
const passwordInput = document.getElementById("password")
const requestList = document.getElementById("requestList")
const newUserForm = document.getElementById("newUserForm")
const userType = document.getElementById("userType")
const username = document.getElementById("username")
const email = document.getElementById("email")
const locationInput = document.getElementById("location")
const phone = document.getElementById("phone")
const birthdate = document.getElementById("birthdate")

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

let pendingRequests = []

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

async function confirmPopup(message, options = {}) {
  if (window.showAdminConfirm) {
    return window.showAdminConfirm(message, options)
  }

  return confirm(message)
}

function getToken() {
  return localStorage.getItem("token")
}

function isApproved(value) {
  return value === true || value === 1 || value === "1" || value === "true" || value === "t"
}

function getRoleLabel(role) {
  const labels = {
    admin: "Administrador",
    profesional: "Cuidador Profesional",
    familiar: "Cuidador Familiar",
    cuidador_profesional: "Cuidador Profesional",
    cuidador_familiar: "Cuidador Familiar"
  }

  return labels[role] || role || "Sin rol"
}

function normalizeRole(role) {
  const roles = {
    "cuidador-profesional": "cuidador_profesional",
    "cuidador-familiar": "cuidador_familiar"
  }

  return roles[role] || role
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;")
}

function getErrorMessage(data, fallback) {
  if (data?.message) return data.message

  if (data?.errors) {
    const firstError = Object.values(data.errors).flat()[0]
    if (firstError) return firstError
  }

  return fallback
}

function renderRequestState(message, className = "empty-requests") {
  if (!requestList) return

  requestList.innerHTML = `
    <div class="${className}">
      ${escapeHtml(message)}
    </div>
  `
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
  newUserForm?.querySelectorAll("input, select, button").forEach((element) => {
    element.disabled = disabled
  })
}

function clearForm() {
  newUserForm?.reset()
  passwordInput.type = "password"

  const icon = togglePassword?.querySelector("i")
  if (icon) {
    icon.classList.add("bx-hide")
    icon.classList.remove("bx-show")
  }
}

async function createUser() {
  const token = getToken()

  if (!token) {
    await showPopup("Inicia sesion como administrador para crear usuarios.", { variant: "error" })
    navigateTo("../../index.html")
    return
  }

  const payload = {
    name: username.value.trim(),
    email: email.value.trim(),
    password: passwordInput.value.trim(),
    role: normalizeRole(userType.value),
    location: locationInput.value.trim() || null,
    phone: phone.value.trim() || null,
    birthdate: birthdate.value || null
  }

  if (!payload.name || !payload.email || !payload.password || !payload.role) {
    await showPopup("Completa tipo de usuario, nombre, correo y contrasena.", { variant: "error" })
    return
  }

  setFormDisabled(true)

  try {
    const data = await fetchJson(`${API_URL}/admin/users`, {
      method: "POST",
      headers: getAuthHeaders(true),
      body: JSON.stringify(payload)
    })

    await showPopup(data.message || "Usuario creado correctamente.")
    clearForm()
    await loadPendingRequests()
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
  } finally {
    setFormDisabled(false)
  }
}

async function loadPendingRequests() {
  const token = getToken()

  if (!token) {
    renderRequestState("Inicia sesion como administrador para ver las solicitudes.")
    return
  }

  renderRequestState("Cargando solicitudes...", "loading-requests")

  try {
    const data = await fetchJson(`${API_URL}/admin/users`, {
      headers: {
        "Accept": "application/json",
        "Authorization": `Bearer ${token}`
      }
    })

    pendingRequests = (data.users || []).filter((user) => {
      return user.role !== "admin" && !isApproved(user.is_approved)
    })

    renderRequests()
  } catch (error) {
    renderRequestState(error.message)
  }
}

async function approveRequest(userId) {
  const token = getToken()

  try {
    const data = await fetchJson(`${API_URL}/admin/users/${userId}/approve`, {
      method: "PATCH",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Authorization": `Bearer ${token}`
      }
    })

    await showPopup(data.message || "Usuario aprobado correctamente.")
    await loadPendingRequests()
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
  }
}

async function rejectRequest(userId) {
  const confirmed = await confirmPopup("Deseas rechazar esta solicitud de cuenta?", {
    title: "Rechazar solicitud",
    confirmText: "Rechazar",
  })

  if (!confirmed) {
    return
  }

  const token = getToken()

  try {
    const data = await fetchJson(`${API_URL}/admin/users/${userId}/reject`, {
      method: "DELETE",
      headers: {
        "Accept": "application/json",
        "Authorization": `Bearer ${token}`
      }
    })

    await showPopup(data.message || "Solicitud rechazada correctamente.")
    await loadPendingRequests()
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
  }
}

async function showRequestDetails(userId) {
  const request = pendingRequests.find((item) => String(item.id) === String(userId))

  if (!request) return

  await showPopup([
    `Nombre: ${request.name || "Sin nombre"}`,
    `Rol: ${getRoleLabel(request.role)}`,
    `Correo: ${request.email || "Sin correo"}`,
    `Telefono: ${request.phone || "Sin telefono"}`,
    `Locacion: ${request.location || "Sin locacion"}`,
    `Fecha de nacimiento: ${request.birthdate || "Sin fecha"}`
  ].join("\n"), { title: "Informacion de solicitud" })
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

function renderRequests() {
  if (!requestList) return

  requestList.innerHTML = ""

  if (!pendingRequests.length) {
    renderRequestState("No hay solicitudes pendientes.")
    return
  }

  pendingRequests.forEach((request) => {
    const card = document.createElement("article")
    card.className = "request-card"

    card.innerHTML = `
      <div class="request-top">
        <div>
          <h3 class="request-title">${escapeHtml(request.name)}</h3>
          <div class="request-role">${escapeHtml(getRoleLabel(request.role))}</div>
        </div>
      </div>

      <div class="request-info">
        <span>Correo: ${escapeHtml(request.email || "Sin correo")}</span>
        <span>Telefono: ${escapeHtml(request.phone || "Sin telefono")}</span>
      </div>

      <div class="request-actions">
        <button class="secondary-button" data-id="${request.id}" type="button">Ver informacion</button>
        <button class="accept-button" data-id="${request.id}" type="button">Aceptar</button>
        <button class="deny-button" data-id="${request.id}" type="button">Rechazar</button>
      </div>
    `

    requestList.appendChild(card)
  })
}

if (requestList) {
  requestList.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-id]")
    if (!button) return

    if (button.classList.contains("secondary-button")) {
      showRequestDetails(button.dataset.id)
      return
    }

    if (button.classList.contains("accept-button")) {
      approveRequest(button.dataset.id)
      return
    }

    if (button.classList.contains("deny-button")) {
      rejectRequest(button.dataset.id)
    }
  })
}

if (newUserForm) {
  newUserForm.addEventListener("submit", (event) => {
    event.preventDefault()
    createUser()
  })
}

loadPendingRequests()
