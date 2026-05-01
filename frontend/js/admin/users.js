const userSearchInput = document.getElementById("userSearchInput")
const usersTableBody = document.getElementById("usersTableBody")

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

let usersData = []

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
    const response = await fetch(`${API_URL}/users`, {
      headers: {
        "Accept": "application/json"
      }
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || "No se pudieron cargar los usuarios.")
    }

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
    alert("Inicia sesion para aprobar usuarios.")
    return
  }

  try {
    const response = await fetch(`${API_URL}/admin/users/${userId}/approve`, {
      method: "PATCH",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Authorization": `Bearer ${token}`
      }
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || "No se pudo aprobar el usuario.")
    }

    alert(data.message || "Usuario aprobado correctamente.")
    await loadUsers()
  } catch (error) {
    alert(error.message)
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

      <div class="user-cell" data-label="Telefono">
        ${escapeHtml(user.phone || "Sin telefono")}
      </div>

      <div class="user-cell" data-label="Estado">
        <span class="status-badge ${getStatusClass(status)}">${status}</span>
      </div>

      <div class="user-cell" data-label="Accion">
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
