(() => {
  const OPTIONS = Object.freeze({ expectedRoles: ["admin"], fallbackError: "No se pudo completar la solicitud." })
  const api = () => window.CuidadoApi

  function load(filter) {
    return Promise.all([
      api().fetchJson(`/admin/medication-statistics?filter=${encodeURIComponent(filter)}`, OPTIONS),
      api().fetchJson("/admin/older-adults", OPTIONS),
    ])
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

  window.MedicationStatsService = Object.freeze({ adjustStock, load, removeInventory, saveInventory })
})()
