const olderAdultSearchInput = document.getElementById("olderAdultSearchInput")
const olderAdultsTableBody = document.getElementById("olderAdultsTableBody")

let olderAdultsData = []


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
  const token = window.CuidadoApi.getToken(["admin"])

  if (!token) {
    renderEmpty("Inicia sesión como administrador para ver adultos mayores.")
    return
  }

  try {
    const data = await window.CuidadoApi.fetchJson("/admin/older-adults", {
      expectedRoles: ["admin"],
      fallbackError: "No se pudieron cargar los adultos mayores.",
    })

    olderAdultsData = data.older_adults || []
    renderOlderAdults(olderAdultsData)
  } catch (error) {
    renderEmpty(error.message)
  }
}

function renderEmpty(message) {
  olderAdultsTableBody.replaceChildren(window.CuidadoUi.element("div", "empty-state", message))
}

function renderOlderAdults(list) {
  const el = window.CuidadoUi.element
  olderAdultsTableBody.replaceChildren()
  if (!list.length) return renderEmpty("No se encontraron adultos mayores.")
  list.forEach((adult) => {
    const cell = (label, text, children = []) => {
      const node = el("div", "older-adult-cell", text, children)
      node.dataset.label = label
      return node
    }
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
        if (window.navigateWithLoading) window.navigateWithLoading(destination)
        else window.location.assign(destination)
      })
      actions.append(button)
    }
    olderAdultsTableBody.append(el("article", "older-adult-row", null, [
      name, cell("Edad", adult.age ?? "Sin edad"), cell("Encargado", adult.caregiver_family || "Sin encargado"),
      cell("Habitacion", adult.room || "Sin habitacion"),
      cell("Estado", null, [el("span", `status-badge ${getStatusClass(adult.status)}`, adult.status || "Estable")]), actions,
    ]))
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
