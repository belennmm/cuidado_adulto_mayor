const olderAdultSearchInput = document.getElementById("olderAdultSearchInput")
const olderAdultsTableBody = document.getElementById("olderAdultsTableBody")

const olderAdultsData = [
  {
    id: 1,
    name: "Elena Rodríguez",
    age: 78,
    caregiver: "Carlos Méndez",
    room: "A-101",
    status: "Estable"
  },
  {
    id: 2,
    name: "Miguel Herrera",
    age: 81,
    caregiver: "María Castillo",
    room: "B-204",
    status: "Atención"
  },
  {
    id: 3,
    name: "Rosa Pérez",
    age: 74,
    caregiver: "Lucía Herrera",
    room: "A-115",
    status: "Estable"
  },
  {
    id: 4,
    name: "Jorge Ramírez",
    age: 85,
    caregiver: "Carlos Méndez",
    room: "C-302",
    status: "Crítico"
  },
  {
    id: 5,
    name: "Marta López",
    age: 79,
    caregiver: "Sofía Ramírez",
    room: "B-210",
    status: "Atención"
  },
  {
    id: 6,
    name: "Ricardo Gómez",
    age: 83,
    caregiver: "María Castillo",
    room: "A-122",
    status: "Estable"
  }
]

function getStatusClass(status) {
  if (status === "Estable") return "status-stable"
  if (status === "Atención") return "status-attention"
  return "status-critical"
}

function renderOlderAdults(list) {
  olderAdultsTableBody.innerHTML = ""

  if (!list.length) {
    olderAdultsTableBody.innerHTML = `
      <div class="empty-state">
        No se encontraron adultos mayores.
      </div>
    `
    return
  }

  list.forEach((olderAdult) => {
    const row = document.createElement("article")
    row.className = "older-adult-row"

    row.innerHTML = `
      <div class="older-adult-cell older-adult-name" data-label="Nombre">
        <div class="older-adult-avatar"></div>
        <span>${olderAdult.name}</span>
      </div>

      <div class="older-adult-cell" data-label="Edad">
        ${olderAdult.age}
      </div>

      <div class="older-adult-cell" data-label="Encargado">
        ${olderAdult.caregiver}
      </div>

      <div class="older-adult-cell" data-label="Habitación">
        ${olderAdult.room}
      </div>

      <div class="older-adult-cell" data-label="Estado">
        <span class="status-badge ${getStatusClass(olderAdult.status)}">${olderAdult.status}</span>
      </div>

      <div class="older-adult-cell" data-label="Acción">
        <button class="edit-button" data-id="${olderAdult.id}">Editar</button>
      </div>
    `

    olderAdultsTableBody.appendChild(row)
  })

  const editButtons = document.querySelectorAll(".edit-button")

  editButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const olderAdultId = button.dataset.id
      window.location.href = `./edit-adulto-mayor.html?id=${olderAdultId}`
    })
  })
}

function filterOlderAdults() {
  const searchValue = olderAdultSearchInput.value.trim().toLowerCase()

  const filteredOlderAdults = olderAdultsData.filter((olderAdult) => {
    return (
      olderAdult.name.toLowerCase().includes(searchValue) ||
      String(olderAdult.age).includes(searchValue) ||
      olderAdult.caregiver.toLowerCase().includes(searchValue) ||
      olderAdult.room.toLowerCase().includes(searchValue) ||
      olderAdult.status.toLowerCase().includes(searchValue)
    )
  })

  renderOlderAdults(filteredOlderAdults)
}

if (olderAdultSearchInput) {
  olderAdultSearchInput.addEventListener("input", filterOlderAdults)
}

renderOlderAdults(olderAdultsData)