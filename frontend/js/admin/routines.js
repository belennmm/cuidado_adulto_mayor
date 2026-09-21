(() => {
  const state = { olderAdults: [], activeOlderAdultId: "", currentRoutines: [], editingRoutineId: null }

  const escapeHtml = window.CuidadoUi.escapeHtml

  const setText = window.CuidadoUi.setText

  function setMessage(message, isError = false) {
    window.CuidadoUi.setMessage("adminRoutineMessage", message, {
      type: isError ? "error" : "",
    })
  }

  async function showPopup(message, options = {}) {
    if (window.showAdminAlert) {
      await window.showAdminAlert(message, options)
      return
    }

    console.warn(message)
  }

  function getRequestedAdultId() {
    const params = new URLSearchParams(window.location.search)
    return params.get("older_adult_id") || params.get("id") || ""
  }

  function updateAdultUrl(adultId) {
    if (!adultId) return
    const url = new URL(window.location.href)
    url.searchParams.set("older_adult_id", adultId)
    window.history.replaceState({}, "", url)
  }

  const { renderAdultSelector, resetForm, parseActivities, renderEmpty, renderRoutines, renderMedications, renderMeta } = window.AdminRoutinesView.create({ state, escapeHtml, setText })

  async function loadAdults() {
    const data = await window.CuidadoApi.fetchJson("/admin/older-adults", {
      expectedRoles: ["admin"],
      fallbackError: "No se pudo completar la acción.",
    })
    state.olderAdults = data.older_adults || []
  }

  async function loadRoutines() {
    if (!state.activeOlderAdultId) {
      renderMeta(null)
      renderEmpty("adminRoutinesList", "Selecciona un adulto mayor para ver sus rutinas.")
      renderEmpty("adminMedicationsList", "Selecciona un adulto mayor para ver sus medicamentos.")
      setText("adminRoutineTotal", 0)
      setText("adminMedicationTotal", 0)
      return
    }

    const [routineData, adultData] = await Promise.all([
      window.CuidadoApi.fetchJson(`/rutinas?older_adult_id=${encodeURIComponent(state.activeOlderAdultId)}`, {
        expectedRoles: ["admin"],
        fallbackError: "No se pudo completar la acción.",
      }),
      window.CuidadoApi.fetchJson(`/admin/older-adults/${encodeURIComponent(state.activeOlderAdultId)}`, {
        expectedRoles: ["admin"],
        fallbackError: "No se pudo completar la acción.",
      }),
    ])
    const adult = adultData.older_adult || state.olderAdults.find((item) => String(item.id) === String(state.activeOlderAdultId))

    renderMeta(adult)
    renderMedications(adult)
    renderRoutines(routineData.rutinas || [])
    updateAdultUrl(state.activeOlderAdultId)
  }

  async function saveRoutine() {
    if (!state.activeOlderAdultId) {
      const message = "Selecciona un adulto mayor antes de guardar una rutina."
      setMessage(message, true)
      await showPopup(message, { variant: "error" })
      return
    }

    const saveButton = document.getElementById("adminSaveRoutineButton")
    const nombre = document.getElementById("adminRoutineName")?.value.trim() || ""
    const horario = document.getElementById("adminRoutineSchedule")?.value.trim() || ""
    const actividades = parseActivities(document.getElementById("adminRoutineActivities")?.value)

    if (!nombre || !horario || !actividades.length) {
      const message = "Completa nombre, horario y al menos una actividad."
      setMessage(message, true)
      await showPopup(message, { variant: "error" })
      return
    }

    try {
      if (!window.CuidadoApi.getToken(["admin"])) {
        throw new Error("Inicia sesión como administrador para gestionar rutinas.")
      }

      if (saveButton) {
        saveButton.disabled = true
        saveButton.textContent = "Guardando..."
      }

      await window.CuidadoApi.fetchJson(state.editingRoutineId ? `/rutinas/${state.editingRoutineId}` : "/rutinas", {
        method: state.editingRoutineId ? "PUT" : "POST",
        body: JSON.stringify({
          nombre,
          horario,
          actividades,
          ...(state.editingRoutineId ? {} : { older_adult_id: state.activeOlderAdultId }),
        }),
        expectedRoles: ["admin"],
        fallbackError: "No se pudo completar la acción.",
      })

      const wasEditing = Boolean(state.editingRoutineId)
      resetForm()
      const message = wasEditing ? "Rutina actualizada correctamente." : "Rutina creada correctamente."
      setMessage(message)
      await loadRoutines()
      await showPopup(message, { variant: "success" })
    } catch (error) {
      setMessage(error.message, true)
      await showPopup(error.message, { variant: "error" })
    } finally {
      if (saveButton) {
        saveButton.disabled = false
        saveButton.textContent = state.editingRoutineId ? "Guardar cambios" : "Guardar rutina"
      }
    }
  }

  function startEditRoutine(routineId) {
    const routine = state.currentRoutines.find((item) => String(item.id) === String(routineId))
    if (!routine) return

    state.editingRoutineId = routine.id
    document.getElementById("adminRoutineName").value = routine.nombre || ""
    document.getElementById("adminRoutineSchedule").value = routine.horario || ""
    document.getElementById("adminRoutineActivities").value = Array.isArray(routine.actividades) ? routine.actividades.join("\n") : ""
    setText("adminRoutineFormTitle", "Editar rutina")

    const saveButton = document.getElementById("adminSaveRoutineButton")
    const cancelButton = document.getElementById("adminCancelRoutineEdit")
    if (saveButton) saveButton.textContent = "Guardar cambios"
    if (cancelButton) cancelButton.hidden = false

    setMessage("")
    document.getElementById("adminRoutineForm")?.scrollIntoView({ behavior: "smooth", block: "start" })
  }

  async function deleteRoutine(routineId) {
    const confirmed = window.showAdminConfirm
      ? await window.showAdminConfirm("Deseas eliminar esta rutina?", {
          title: "Eliminar rutina",
          confirmText: "Eliminar",
        })
      : false

    if (!confirmed) return

    try {
      if (!window.CuidadoApi.getToken(["admin"])) {
        throw new Error("Inicia sesión como administrador para gestionar rutinas.")
      }

      await window.CuidadoApi.fetchJson(`/rutinas/${routineId}`, {
        method: "DELETE",
        expectedRoles: ["admin"],
        fallbackError: "No se pudo completar la acción.",
      })

      if (String(state.editingRoutineId) === String(routineId)) resetForm()
      const message = "Rutina eliminada correctamente."
      setMessage(message)
      await loadRoutines()
      await showPopup(message, { variant: "success" })
    } catch (error) {
      setMessage(error.message, true)
      await showPopup(error.message, { variant: "error" })
    }
  }

  async function initialize() {
    try {
      if (!window.CuidadoApi.getToken(["admin"])) {
        throw new Error("Inicia sesión como administrador para gestionar rutinas.")
      }

      await loadAdults()

      if (!state.olderAdults.length) {
        renderAdultSelector()
        renderEmpty("adminRoutinesList", "No hay adultos mayores registrados.")
        renderEmpty("adminMedicationsList", "No hay adultos mayores registrados.")
        return
      }

      const requestedAdultId = getRequestedAdultId()
      const hasRequestedAdult = state.olderAdults.some((adult) => String(adult.id) === String(requestedAdultId))
      state.activeOlderAdultId = hasRequestedAdult ? requestedAdultId : String(state.olderAdults[0].id)

      renderAdultSelector()
      await loadRoutines()
    } catch (error) {
      renderEmpty("adminRoutinesList", error.message)
      renderEmpty("adminMedicationsList", error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("adminRoutineAdultSelector")?.addEventListener("change", async (event) => {
      state.activeOlderAdultId = event.target.value
      resetForm()
      setMessage("")
      await loadRoutines()
    })

    document.getElementById("adminRoutineForm")?.addEventListener("submit", async (event) => {
      event.preventDefault()
      await saveRoutine()
    })

    document.getElementById("adminCancelRoutineEdit")?.addEventListener("click", () => {
      resetForm()
      setMessage("")
    })

    document.getElementById("adminRoutinesList")?.addEventListener("click", async (event) => {
      const button = event.target.closest("button[data-routine-action][data-id]")
      if (!button) return

      if (button.dataset.routineAction === "edit") {
        startEditRoutine(button.dataset.id)
        return
      }

      if (button.dataset.routineAction === "delete") {
        await deleteRoutine(button.dataset.id)
      }
    })

    initialize()
  })
})()
