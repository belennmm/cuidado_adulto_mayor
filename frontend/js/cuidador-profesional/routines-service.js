(() => {
  const api = () => window.ProfessionalCare
  const encodeId = (value) => encodeURIComponent(value)

  async function loadAdults() {
    const data = await api().fetchJson("/professional/older-adults")
    return data.older_adults || []
  }

  function loadDashboard(olderAdultId) {
    return Promise.all([
      api().fetchJson(`/professional/routines?older_adult_id=${encodeId(olderAdultId)}`),
      api().fetchJson(`/professional/routine-notes?older_adult_id=${encodeId(olderAdultId)}`),
      api().fetchJson(`/rutinas?older_adult_id=${encodeId(olderAdultId)}`),
    ])
  }

  function saveRoutine({ id, olderAdultId, nombre, horario, actividades }) {
    return api().fetchJson(id ? `/rutinas/${id}` : "/rutinas", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify({ nombre, horario, actividades, ...(id ? {} : { adulto_mayor_id: olderAdultId }) }),
    })
  }

  const deleteRoutine = (id) => api().fetchJson(`/rutinas/${id}`, { method: "DELETE" })
  const completeActivity = (id, index) => api().fetchJson(`/rutinas/${id}/completar`, {
    method: "PATCH", body: JSON.stringify({ actividad_index: Number(index) }),
  })
  const deleteNote = (id) => api().fetchJson(`/professional/routine-notes/${id}`, { method: "DELETE" })

  function saveNote({ id, olderAdultId, content }) {
    return api().fetchJson(id ? `/professional/routine-notes/${id}` : "/professional/routine-notes", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(id ? { content } : { older_adult_id: olderAdultId, content }),
    })
  }

  window.ProfessionalRoutinesService = Object.freeze({
    completeActivity, deleteNote, deleteRoutine, loadAdults, loadDashboard, saveNote, saveRoutine,
  })
})()
