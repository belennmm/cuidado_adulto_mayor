const olderAdultSearchInput = document.getElementById("olderAdultSearchInput")
const olderAdultsTableBody = document.getElementById("olderAdultsTableBody")

let olderAdultsData = []
let adultsLoadSequence = 0


function getStatusClass(status) {
  const normalizedStatus = String(status || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()

  if (normalizedStatus === "estable") return "status-stable"
  if (normalizedStatus === "atencion") return "status-attention"
  return "status-critical"
}

async function loadOlderAdults(page = 1) {
  const sequence = ++adultsLoadSequence
  const hasSession = window.CuidadoApi.hasSession(["admin"])

  if (!hasSession) {
    renderEmpty("Inicia sesión como administrador para ver adultos mayores.")
    return
  }

  try {
    const data = await window.CuidadoApi.fetchJson(window.CuidadoUi.pagePath("/admin/older-adults", page, { q: olderAdultSearchInput?.value.trim() || "" }), {
      expectedRoles: ["admin"],
      fallbackError: "No se pudieron cargar los adultos mayores.",
    })

    if (sequence !== adultsLoadSequence) return
    olderAdultsData = data.older_adults || []
    renderOlderAdults(olderAdultsData)
    window.CuidadoUi.pagination(olderAdultsTableBody, data.pagination, loadOlderAdults)
  } catch (error) {
    if (sequence !== adultsLoadSequence) return
    renderEmpty(error.message)
  }
}

function renderEmpty(message) {
  window.CuidadoUi.renderCollection(olderAdultsTableBody, [], () => null, message)
}

function renderOlderAdults(list) {
  const el = window.CuidadoUi.element
  const cell = (label, text, children = []) => window.CuidadoUi.cell(label, text, children, "older-adult-cell")
  window.CuidadoUi.renderCollection(olderAdultsTableBody, list, (adult) => {
    const name = cell("Nombre", null, [el("div", "older-adult-avatar"), el("span", "", adult.full_name)])
    name.classList.add("older-adult-name")
    const actions = cell("Acción", null)
    actions.classList.add("older-adult-actions")
    for (const [className, label, page, parameter] of [
      ["edit-button", "Editar", "edit-adulto-mayor.html", "id"],
      ["routine-button", "Rutina", "routines.html", "older_adult_id"],
    ]) {
      const button = el("button", className, label)
      button.type = "button"
      button.dataset.id = String(adult.id ?? "")
      button.addEventListener("click", () => {
        const destination = `./${page}?${new URLSearchParams({ [parameter]: button.dataset.id })}`
        window.CuidadoUi.navigate(destination)
      })
      actions.append(button)
    }
    return el("article", "older-adult-row", null, [
      name, cell("Edad", adult.age ?? "Sin edad"), cell("Encargado", adult.caregiver_family || "Sin encargado"),
      cell("Habitacion", adult.room || "Sin habitacion"),
      cell("Estado", null, [el("span", `status-badge ${getStatusClass(adult.status)}`, adult.status || "Estable")]), actions,
    ])
  }, "No se encontraron adultos mayores.")
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
  let timer
  olderAdultSearchInput.addEventListener("input", () => {
    adultsLoadSequence++
    clearTimeout(timer)
    timer = setTimeout(() => loadOlderAdults(1), 400)
  })
}

loadOlderAdults()
