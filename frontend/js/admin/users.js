const userSearchInput = document.getElementById("userSearchInput")
const usersTableBody = document.getElementById("usersTableBody")

const usersData = [
  {
    id: 1,
    name: "Ana López",
    role: "Administrador",
    email: "ana.lopez@correo.com",
    phone: "4567-1234",
    status: "Activo"
  },
  {
    id: 2,
    name: "Carlos Méndez",
    role: "Cuidador Profesional",
    email: "carlos.mendez@correo.com",
    phone: "4987-1122",
    status: "Activo"
  },
  {
    id: 3,
    name: "Sofía Ramírez",
    role: "Cuidador Familiar",
    email: "sofia.ramirez@correo.com",
    phone: "5123-8844",
    status: "Pendiente"
  },
  {
    id: 4,
    name: "María Castillo",
    role: "Cuidador Profesional",
    email: "maria.castillo@correo.com",
    phone: "5344-1098",
    status: "Activo"
  },
  {
    id: 5,
    name: "José Pérez",
    role: "Administrador",
    email: "jose.perez@correo.com",
    phone: "4777-6611",
    status: "Inactivo"
  },
  {
    id: 6,
    name: "Lucía Herrera",
    role: "Cuidador Familiar",
    email: "lucia.herrera@correo.com",
    phone: "5890-7766",
    status: "Activo"
  }
]

function getStatusClass(status) {
  if (status === "Activo") return "status-active"
  if (status === "Pendiente") return "status-pending"
  return "status-inactive"
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
    const row = document.createElement("article")
    row.className = "user-row"

    row.innerHTML = `
      <div class="user-cell user-name" data-label="Nombre">
        <div class="user-avatar"></div>
        <span>${user.name}</span>
      </div>

      <div class="user-cell" data-label="Rol">
        ${user.role}
      </div>

      <div class="user-cell" data-label="Correo">
        ${user.email}
      </div>

      <div class="user-cell" data-label="Teléfono">
        ${user.phone}
      </div>

      <div class="user-cell" data-label="Estado">
        <span class="status-badge ${getStatusClass(user.status)}">${user.status}</span>
      </div>

      <div class="user-cell" data-label="Acción">
        <button class="edit-button" data-id="${user.id}">Editar</button>
      </div>
    `

    usersTableBody.appendChild(row)
  })

  const editButtons = document.querySelectorAll(".edit-button")

  editButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const userId = button.dataset.id
      window.location.href = `./edit-user.html?id=${userId}`
    })
  })
}

function filterUsers() {
  const searchValue = userSearchInput.value.trim().toLowerCase()

  const filteredUsers = usersData.filter((user) => {
    return (
      user.name.toLowerCase().includes(searchValue) ||
      user.role.toLowerCase().includes(searchValue) ||
      user.email.toLowerCase().includes(searchValue) ||
      user.phone.toLowerCase().includes(searchValue) ||
      user.status.toLowerCase().includes(searchValue)
    )
  })

  renderUsers(filteredUsers)
}

if (userSearchInput) {
  userSearchInput.addEventListener("input", filterUsers)
}

renderUsers(usersData)