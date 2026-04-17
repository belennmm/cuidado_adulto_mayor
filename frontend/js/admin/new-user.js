const togglePassword = document.getElementById("togglePassword")
const passwordInput = document.getElementById("password")
const requestList = document.getElementById("requestList")
const newUserForm = document.getElementById("newUserForm")

const pendingRequests = [
  {
    id: 1,
    name: "Fernanda Morales",
    role: "Cuidador Familiar",
    email: "fernanda.morales@correo.com",
    phone: "4455-2201"
  },
  {
    id: 2,
    name: "Luis Herrera",
    role: "Cuidador Profesional",
    email: "luis.herrera@correo.com",
    phone: "5588-9981"
  }
]

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
  requestList.innerHTML = ""

  if (!pendingRequests.length) {
    requestList.innerHTML = `
      <div class="empty-requests">
        No hay solicitudes pendientes.
      </div>
    `
    return
  }

  pendingRequests.forEach((request) => {
    const card = document.createElement("article")
    card.className = "request-card"

    card.innerHTML = `
      <div class="request-top">
        <div>
          <h3 class="request-title">${request.name}</h3>
          <div class="request-role">${request.role}</div>
        </div>
      </div>

      <div class="request-info">
        <span>Correo: ${request.email}</span>
        <span>Teléfono: ${request.phone}</span>
      </div>

      <div class="request-actions">
        <button class="secondary-button" data-id="${request.id}">Ver información</button>
        <button class="accept-button" data-id="${request.id}">Aceptar</button>
        <button class="deny-button" data-id="${request.id}">Denegar</button>
      </div>
    `

    requestList.appendChild(card)
  })

  const viewButtons = document.querySelectorAll(".secondary-button")
  const acceptButtons = document.querySelectorAll(".accept-button")
  const denyButtons = document.querySelectorAll(".deny-button")

  viewButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const requestId = button.dataset.id
      alert(`Aquí luego aprobar solicitud ${requestId}`)
    })
  })

  acceptButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const requestId = Number(button.dataset.id)
      alert(`Solicitud ${requestId} aceptada`)
    })
  })

  denyButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const requestId = Number(button.dataset.id)
      alert(`Solicitud ${requestId} denegada`)
    })
  })
}

if (newUserForm) {
  newUserForm.addEventListener("submit", (event) => {
    event.preventDefault()
    alert("Aquí después conectarás la creación del usuario con la base de datos")
  })
}

renderRequests()