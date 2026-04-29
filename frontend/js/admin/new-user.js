const togglePassword = document.getElementById("togglePassword")
const passwordInput = document.getElementById("password")
const requestList = document.getElementById("requestList")
const newUserForm = document.getElementById("newUserForm")

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

let pendingRequests = []

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

    alert(data.message || "Usuario aprobado correctamente.")
    await loadPendingRequests()
  } catch (error) {
    alert(error.message)
  }
}

async function rejectRequest(userId) {
  const confirmed = confirm("Deseas rechazar esta solicitud de cuenta?")

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

    alert(data.message || "Solicitud rechazada correctamente.")
    await loadPendingRequests()
  } catch (error) {
    alert(error.message)
  }
}

function showRequestDetails(userId) {
  const request = pendingRequests.find((item) => String(item.id) === String(userId))

  if (!request) return

  alert([
    `Nombre: ${request.name || "Sin nombre"}`,
    `Rol: ${getRoleLabel(request.role)}`,
    `Correo: ${request.email || "Sin correo"}`,
    `Telefono: ${request.phone || "Sin telefono"}`,
    `Locacion: ${request.location || "Sin locacion"}`,
    `Fecha de nacimiento: ${request.birthdate || "Sin fecha"}`
  ].join("\n"))
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
    alert("Aqui despues conectaras la creacion del usuario con la base de datos")
  })
}

loadPendingRequests()
