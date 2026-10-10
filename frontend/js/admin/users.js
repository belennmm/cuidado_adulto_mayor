const userSearchInput = document.getElementById("userSearchInput")
const usersTableBody = document.getElementById("usersTableBody")

let usersData = []

async function showPopup(message, options = {}) {
  if (window.showAdminAlert) {
    await window.showAdminAlert(message, options)
    return
  }

  console.warn(message)
}

const isApproved = window.CuidadoForms.isApproved

const getRoleLabel = window.CuidadoUi.getRoleLabel

function getStatus(user) {
  if (user.role === "admin") return "Activo"
  return isApproved(user.is_approved) ? "Activo" : "Pendiente"
}

function getStatusClass(status) {
  if (status === "Activo") return "status-active"
  if (status === "Pendiente") return "status-pending"
  return "status-inactive"
}


async function loadUsers() {
  try {
    const data = await window.CuidadoApi.fetchJson("/admin/users", {
      expectedRoles: ["admin"],
      fallbackError: "No se pudieron cargar los usuarios.",
    })

    usersData = data.users || []
    renderUsers(usersData)
  } catch (error) {
    usersTableBody.replaceChildren(window.CuidadoUi.element("div", "empty-state", error.message))
  }
}

async function approveUser(userId) {
  const token = window.CuidadoApi.getToken(["admin"])

  if (!token) {
    await showPopup("Inicia sesión para aprobar usuarios.", { variant: "error" })
    return
  }

  try {
    const data = await window.CuidadoApi.fetchJson(`/admin/users/${encodeURIComponent(userId)}/approve`, {
      method: "PATCH",
      expectedRoles: ["admin"],
      fallbackError: "No se pudo aprobar el usuario.",
    })

    await showPopup(data.message || "Usuario aprobado correctamente.", { variant: "success" })
    await loadUsers()
  } catch (error) {
    await showPopup(error.message, { variant: "error" })
  }
}

function renderUsers(list) {
  const el = window.CuidadoUi.element
  usersTableBody.replaceChildren()
  if (!list.length) {
    usersTableBody.append(el("div", "empty-state", "No se encontraron usuarios."))
    return
  }
  list.forEach((user) => {
    const status = getStatus(user)
    const cell = (label, text, children = []) => {
      const node = el("div", "user-cell", text, children)
      node.dataset.label = label
      return node
    }
    const name = cell("Nombre", null, [el("div", "user-avatar"), el("span", "", user.name)])
    name.classList.add("user-name")
    const button = el("button", status === "Pendiente" ? "approve-button" : "edit-button", status === "Pendiente" ? "Aprobar" : "Editar")
    button.type = "button"
    button.dataset.id = String(user.id ?? "")
    button.addEventListener("click", () => {
      if (status === "Pendiente") return approveUser(button.dataset.id)
      const destination = `./edit-user.html?${new URLSearchParams({ id: button.dataset.id })}`
      if (window.navigateWithLoading) window.navigateWithLoading(destination)
      else window.location.assign(destination)
    })
    usersTableBody.append(el("article", "user-row", null, [
      name, cell("Rol", getRoleLabel(user.role)), cell("Correo", user.email), cell("Teléfono", user.phone || "Sin teléfono"),
      cell("Estado", null, [el("span", `status-badge ${getStatusClass(status)}`, status)]), cell("Acción", null, [button]),
    ]))
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
