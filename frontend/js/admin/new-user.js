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

const userForm = window.AdminUserForm.create({
  form: newUserForm,
  togglePassword,
  fields: { name: username, email, password: passwordInput, role: userType, location: locationInput, phone, birthdate },
})

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

  console.warn(message)
}

async function confirmPopup(message, options = {}) {
  if (window.showAdminConfirm) {
    return window.showAdminConfirm(message, options)
  }

  console.warn(message, options)
  return false
}

const isApproved = window.CuidadoForms.isApproved

const getRoleLabel = window.CuidadoUi.getRoleLabel

const escapeHtml = window.CuidadoUi.escapeHtml

function renderRequestState(message, className = "empty-requests") {
  if (!requestList) return

  requestList.innerHTML = `
    <div class="${className}">
      ${escapeHtml(message)}
    </div>
  `
}

function setFormDisabled(disabled) {
  userForm.setDisabled(disabled)
}

function clearForm() {
  userForm.reset()
}

async function createUser() {
  if (!window.CuidadoApi.getToken(["admin"])) {
    await showPopup("Inicia sesión como administrador para crear usuarios.", { variant: "error" })
    navigateTo("../../index.html")
    return
  }

  const payload = userForm.readPayload({ includePassword: true })

  if (window.CuidadoForms.findMissing(payload, ["name", "email", "password", "role"]).length) {
    await showPopup("Completa tipo de usuario, nombre, correo y contraseña.", { variant: "error" })
    return
  }

  setFormDisabled(true)

  try {
    const data = await window.CuidadoApi.fetchJson("/admin/users", {
      method: "POST",
      body: JSON.stringify(payload),
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la solicitud.",
    })

    await showPopup(data.message || "Usuario creado correctamente.", { variant: "success" })
    clearForm()
    await loadPendingRequests()
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
  } finally {
    setFormDisabled(false)
  }
}

async function loadPendingRequests() {
  if (!window.CuidadoApi.getToken(["admin"])) {
    renderRequestState("Inicia sesión como administrador para ver las solicitudes.")
    return
  }

  renderRequestState("Cargando solicitudes...", "loading-requests")

  try {
    const data = await window.CuidadoApi.fetchJson("/admin/users", {
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la solicitud.",
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
  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/users/${userId}/approve`, {
      method: "PATCH",
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la solicitud.",
    })

    await showPopup(data.message || "Usuario aprobado correctamente.", { variant: "success" })
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

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/users/${userId}/reject`, {
      method: "DELETE",
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la solicitud.",
    })

    await showPopup(data.message || "Solicitud rechazada correctamente.", { variant: "success" })
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
    `Teléfono: ${request.phone || "Sin teléfono"}`,
    `Locacion: ${request.location || "Sin locacion"}`,
    `Fecha de nacimiento: ${request.birthdate || "Sin fecha"}`
  ].join("\n"), { title: "Información de solicitud" })
}

userForm.bindPasswordToggle()

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
        <span>Teléfono: ${escapeHtml(request.phone || "Sin teléfono")}</span>
      </div>

      <div class="request-actions">
        <button class="secondary-button" data-id="${request.id}" type="button">Ver información</button>
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
