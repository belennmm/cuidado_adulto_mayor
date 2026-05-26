const userSearchInput = document.getElementById("userSearchInput")
const usersTableBody = document.getElementById("usersTableBody")

let usersData = []

function getToken() {
  return (window.AuthSession?.getToken() || "")
}

async function showPopup(message, options = {}) {
  if (window.showAdminAlert) {
    await window.showAdminAlert(message, options)
    return
  }

  console.warn(message)
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

function getStatus(user) {
  if (user.role === "admin") return "Activo"
  return isApproved(user.is_approved) ? "Activo" : "Pendiente"
}

function getStatusClass(status) {
  if (status === "Activo") return "status-active"
  if (status === "Pendiente") return "status-pending"
  return "status-inactive"
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;")
}

async function loadUsers() {
  try {
    const data = await window.CuidadoApi.fetchJson("/users", {
      auth: false,
      fallbackError: "No se pudieron cargar los usuarios.",
    })

    usersData = data.users || []
    renderUsers(usersData)
  } catch (error) {
    usersTableBody.innerHTML = `
      <div class="empty-state">
        ${escapeHtml(error.message)}
      </div>
    `
  }
}

async function approveUser(userId) {
  const token = getToken()

  if (!token) {
    await showPopup("Inicia sesión para aprobar usuarios.", { variant: "error" })
    return
  }

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/users/${userId}/approve`, {
      method: "PATCH",
      token,
      fallbackError: "No se pudo aprobar el usuario.",
    })

    await showPopup(data.message || "Usuario aprobado correctamente.", { variant: "success" })
    await loadUsers()
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
  }
}

function renderUsers(list) {
  usersTableBody.innerHTML = ""

  if (!list.length) {
    usersTableBody.innerHTML = `
      <div class="empty-state">
        No se encontraron usuarios.
      </div>
    `
    return
  }

  list.forEach((user) => {
    const status = getStatus(user)
    const row = document.createElement("article")
    row.className = "user-row"

    const actionButton = status === "Pendiente"
      ? `<button class="approve-button" data-id="${user.id}">Aprobar</button>`
      : `<button class="edit-button" data-id="${user.id}">Editar</button>`

    row.innerHTML = `
      <div class="user-cell user-name" data-label="Nombre">
        <div class="user-avatar"></div>
        <span>${escapeHtml(user.name)}</span>
      </div>

      <div class="user-cell" data-label="Rol">
        ${escapeHtml(getRoleLabel(user.role))}
      </div>

      <div class="user-cell" data-label="Correo">
        ${escapeHtml(user.email)}
      </div>

      <div class="user-cell" data-label="Teléfono">
        ${escapeHtml(user.phone || "Sin teléfono")}
      </div>

      <div class="user-cell" data-label="Estado">
        <span class="status-badge ${getStatusClass(status)}">${status}</span>
      </div>

      <div class="user-cell" data-label="Acción">
        ${actionButton}
      </div>
    `

    usersTableBody.appendChild(row)
  })

  document.querySelectorAll(".approve-button").forEach((button) => {
    button.addEventListener("click", () => {
      approveUser(button.dataset.id)
    })
  })

  document.querySelectorAll(".edit-button").forEach((button) => {
    button.addEventListener("click", () => {
      const destination = `./edit-user.html?id=${button.dataset.id}`

      if (window.navigateWithLoading) {
        window.navigateWithLoading(destination)
        return
      }

      window.location.assign(destination)
    })
  })
}

function filterUsers() {
  const searchValue = userSearchInput.value.trim().toLowerCase()

  const filteredUsers = usersData.filter((user) => {
    const status = getStatus(user)

    return (
      String(user.name || "").toLowerCase().includes(searchValue) ||
      getRoleLabel(user.role).toLowerCase().includes(searchValue) ||
      String(user.email || "").toLowerCase().includes(searchValue) ||
      String(user.phone || "").toLowerCase().includes(searchValue) ||
      status.toLowerCase().includes(searchValue)
    )
  })

  renderUsers(filteredUsers)
}

if (userSearchInput) {
  userSearchInput.addEventListener("input", filterUsers)
}

loadUsers()
