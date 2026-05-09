const olderAdultSearchInput = document.getElementById("olderAdultSearchInput")
const olderAdultsTableBody = document.getElementById("olderAdultsTableBody")

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

let olderAdultsData = []

function getToken() {
  return localStorage.getItem("token")
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;")
}

function getStatusClass(status) {
  const normalizedStatus = String(status || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()

  if (normalizedStatus === "estable") return "status-stable"
  if (normalizedStatus === "atencion") return "status-attention"
  return "status-critical"
}

async function loadOlderAdults() {
  const token = getToken()

  if (!token) {
    renderEmpty("Inicia sesion como administrador para ver adultos mayores.")
    return
  }

  try {
    const response = await fetch(`${API_URL}/admin/older-adults`, {
      cache: "no-store",
      headers: {
        "Accept": "application/json",
        "Authorization": `Bearer ${token}`,
      },
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || "No se pudieron cargar los adultos mayores.")
    }

    olderAdultsData = data.older_adults || []
    renderOlderAdults(olderAdultsData)
  } catch (error) {
    renderEmpty(error.message)
  }
}

function renderEmpty(message) {
  olderAdultsTableBody.innerHTML = `
    <div class="empty-state">
      ${escapeHtml(message)}
    </div>
  `
}

function renderOlderAdults(list) {
  olderAdultsTableBody.innerHTML = ""

  if (!list.length) {
    renderEmpty("No se encontraron adultos mayores.")
    return
  }

  list.forEach((olderAdult) => {
    const row = document.createElement("article")
    row.className = "older-adult-row"

    row.innerHTML = `
      <div class="older-adult-cell older-adult-name" data-label="Nombre">
        <div class="older-adult-avatar"></div>
        <span>${escapeHtml(olderAdult.full_name)}</span>
      </div>

      <div class="older-adult-cell" data-label="Edad">
        ${escapeHtml(olderAdult.age || "Sin edad")}
      </div>

      <div class="older-adult-cell" data-label="Encargado">
        ${escapeHtml(olderAdult.caregiver_family || "Sin encargado")}
      </div>

      <div class="older-adult-cell" data-label="Habitacion">
        ${escapeHtml(olderAdult.room || "Sin habitacion")}
      </div>

      <div class="older-adult-cell" data-label="Estado">
        <span class="status-badge ${getStatusClass(olderAdult.status)}">${escapeHtml(olderAdult.status || "Estable")}</span>
      </div>

      <div class="older-adult-cell older-adult-actions" data-label="Accion">
        <button class="edit-button" data-id="${olderAdult.id}">Editar</button>
        <button class="routine-button" data-id="${olderAdult.id}">Rutina</button>
      </div>
    `

    olderAdultsTableBody.appendChild(row)
  })

  const editButtons = document.querySelectorAll(".edit-button")

  editButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const olderAdultId = button.dataset.id
      const destination = `./edit-adulto-mayor.html?id=${olderAdultId}`

      if (window.navigateWithLoading) {
        window.navigateWithLoading(destination)
        return
      }

      window.location.assign(destination)
    })
  })

  document.querySelectorAll(".routine-button").forEach((button) => {
    button.addEventListener("click", () => {
      const destination = `./routines.html?older_adult_id=${button.dataset.id}`

      if (window.navigateWithLoading) {
        window.navigateWithLoading(destination)
        return
      }

      window.location.assign(destination)
    })
  })
}

function filterOlderAdults() {
  const searchValue = olderAdultSearchInput.value.trim().toLowerCase()

  const filteredOlderAdults = olderAdultsData.filter((olderAdult) => {
    return (
      String(olderAdult.full_name || "").toLowerCase().includes(searchValue) ||
      String(olderAdult.age || "").includes(searchValue) ||
      String(olderAdult.caregiver_family || "").toLowerCase().includes(searchValue) ||
      String(olderAdult.room || "").toLowerCase().includes(searchValue) ||
      String(olderAdult.status || "").toLowerCase().includes(searchValue)
    )
  })

  renderOlderAdults(filteredOlderAdults)
}

if (olderAdultSearchInput) {
  olderAdultSearchInput.addEventListener("input", filterOlderAdults)
}

loadOlderAdults()
