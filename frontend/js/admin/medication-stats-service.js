(() => {
  const OPTIONS = Object.freeze({ expectedRoles: ["admin"], fallbackError: "No se pudo completar la solicitud." })
  const api = () => window.CuidadoApi

  function loadStatistics(filter, page = 1) {
    return api().fetchJson(`/admin/medication-statistics?filter=${encodeURIComponent(filter)}${page > 1 ? `&page=${page}` : ""}`, OPTIONS)
  }

  function loadInventory(selection = "", page = 1) {
    const params = new URLSearchParams()
    if (selection === "unassigned") params.set("scope", "unassigned")
    else if (selection) params.set("older_adult_id", selection)
    else params.set("scope", "general")
    if (page > 1) params.set("page", String(page))
    return api().fetchJson(`/admin/medications/inventory?${params}`, OPTIONS)
  }

  function loadOlderAdults() {
    return api().fetchJson("/admin/older-adults", OPTIONS)
  }

  function saveInventory(id, payload) {
    return api().fetchJson(id ? `/admin/medications/inventory/${encodeURIComponent(id)}` : "/admin/medications/inventory", {
      ...OPTIONS, method: id ? "PUT" : "POST", body: JSON.stringify(payload),
    })
  }

  function adjustStock(id, action, amount) {
    return api().fetchJson(`/admin/medications/inventory/${encodeURIComponent(id)}/stock`, {
      ...OPTIONS, method: "PATCH", body: JSON.stringify({ action, amount }),
    })
  }

  function removeInventory(id) {
    return api().fetchJson(`/admin/medications/inventory/${encodeURIComponent(id)}`, { ...OPTIONS, method: "DELETE" })
  }

  window.MedicationStatsService = Object.freeze({ adjustStock, loadInventory, loadOlderAdults, loadStatistics, removeInventory, saveInventory })
})()
