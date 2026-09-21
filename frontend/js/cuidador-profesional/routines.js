(() => {
  const api = window.ProfessionalCare
  let assignedAdults = []
  let activeOlderAdultId = ""
  let editingNoteId = null
  let editingCustomRoutineId = null
  let currentCustomRoutines = []
  const routineForm = window.ProfessionalRoutineForm
  const routineService = window.ProfessionalRoutinesService

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

  const setText = window.CuidadoUi.setText

  function setMessage(message, isError = false) {
    window.CuidadoUi.setMessage("routineNoteMessage", message, {
      type: isError ? "error" : "",
      successClass: "is-success",
      successWhenMessage: true,
    })
  }

  function setCustomRoutineMessage(message, isError = false) {
    window.CuidadoUi.setMessage("customRoutineMessage", message, {
      type: isError ? "error" : "",
      successClass: "is-success",
      successWhenMessage: true,
    })
  }

  const firstValidationMessage = routineForm.firstValidationMessage

  async function showProfessionalAlert(message, options = {}) {
    if (typeof window.showAdminAlert === "function") {
      await window.showAdminAlert(message, options)
      return
    }

    console.warn(message)
  }

  async function showProfessionalConfirm(message, options = {}) {
    if (typeof window.showAdminConfirm === "function") {
      return window.showAdminConfirm(message, options)
    }

    console.warn(message, options)
    return false
  }

  function resetNoteForm() {
    editingNoteId = null
    routineForm.resetNote()
  }

  function resetCustomRoutineForm() {
    editingCustomRoutineId = null
    routineForm.resetCustomRoutine()
  }

  function renderAdultSelector() {
    window.ProfessionalRoutinesView.renderAdultSelector(assignedAdults, activeOlderAdultId)
  }

  const renderMedicineRoutine = window.ProfessionalRoutinesView.renderMedicine

  function renderCustomRoutines(routines) {
    currentCustomRoutines = routines
    window.ProfessionalRoutinesView.renderCustomRoutines(routines)
  }

  function renderNotes(notes) {
    window.ProfessionalRoutinesView.renderNotes(notes)
  }

  function renderEmptyState(message) {
    window.ProfessionalRoutinesView.renderEmpty(message)
  }

  function renderSummary(routineData, olderAdult) {
    window.ProfessionalRoutinesView.renderSummary(routineData, olderAdult)
  }

  function renderWeekRange(week) {
    window.ProfessionalRoutinesView.renderWeekRange(week)
  }

  async function loadAdults() {
    assignedAdults = await routineService.loadAdults()
  }

  async function loadRoutinesAndNotes() {
    if (!activeOlderAdultId) {
      renderEmptyState("No tienes adultos mayores asignados por ahora.")
      return
    }

    try {
      const selectedAdult = assignedAdults.find((adult) => String(adult.id) === String(activeOlderAdultId))
      const [routineData, notesData, customRoutineData] = await routineService.loadDashboard(activeOlderAdultId)

      renderSummary(routineData, selectedAdult)
      renderWeekRange(notesData.week)
      const medicineList = document.getElementById("professionalRoutinesList")
      if (medicineList) {
        medicineList.innerHTML = routineData.routine?.length
          ? routineData.routine.map(renderMedicineRoutine).join("")
          : api.renderEmpty("No hay medicamentos asignados para este adulto mayor.")
      }
      renderNotes(notesData.notes || [])
      renderCustomRoutines(customRoutineData.rutinas || [])
      updateAdultUrl(activeOlderAdultId)
    } catch (error) {
      renderEmptyState(error.message)
    }
  }

  const parseActivities = routineForm.parseActivities
  const isValidSchedule = routineForm.isValidSchedule

  async function saveCustomRoutine() {
    if (!activeOlderAdultId) {
      setCustomRoutineMessage("Selecciona un adulto mayor antes de guardar una rutina.", true)
      return
    }

    const saveButton = document.getElementById("saveCustomRoutineButton")
    const nombre = document.getElementById("customRoutineName")?.value.trim() || ""
    const horario = document.getElementById("customRoutineSchedule")?.value.trim() || ""
    const actividades = parseActivities(document.getElementById("customRoutineActivities")?.value)

    if (!nombre) {
      setCustomRoutineMessage("Ingresa el nombre de la rutina.", true)
      return
    }

    if (!isValidSchedule(horario)) {
      setCustomRoutineMessage("Ingresa un horario valido en formato HH:MM.", true)
      return
    }

    if (!actividades.length) {
      setCustomRoutineMessage("Agrega al menos una actividad.", true)
      return
    }

    try {
      if (saveButton) {
        saveButton.disabled = true
        saveButton.textContent = "Guardando..."
      }

      await routineService.saveRoutine({
        id: editingCustomRoutineId,
        olderAdultId: activeOlderAdultId,
        nombre,
        horario,
        actividades,
      })

      const wasEditing = Boolean(editingCustomRoutineId)
      resetCustomRoutineForm()
      const successMessage = wasEditing ? "Rutina actualizada correctamente." : "Rutina creada correctamente."
      setCustomRoutineMessage(successMessage)
      await loadRoutinesAndNotes()
      await showProfessionalAlert(successMessage, {
        title: "Rutina guardada",
        variant: "success",
      })
    } catch (error) {
      const message = firstValidationMessage(error)
      setCustomRoutineMessage(message, true)
      await showProfessionalAlert(message, {
        title: "No se pudo guardar",
        variant: "error",
      })
    } finally {
      if (saveButton) {
        saveButton.disabled = false
        saveButton.textContent = editingCustomRoutineId ? "Guardar cambios" : "Guardar rutina"
      }
    }
  }

  function startEditCustomRoutine(routineId) {
    const routine = currentCustomRoutines.find((item) => String(item.id) === String(routineId))
    if (!routine) return

    const nameInput = document.getElementById("customRoutineName")
    const scheduleInput = document.getElementById("customRoutineSchedule")
    const activitiesInput = document.getElementById("customRoutineActivities")
    const title = document.getElementById("customRoutineFormTitle")
    const saveButton = document.getElementById("saveCustomRoutineButton")
    const cancelButton = document.getElementById("cancelCustomRoutineEdit")

    editingCustomRoutineId = routine.id
    if (nameInput) nameInput.value = routine.nombre || ""
    if (scheduleInput) scheduleInput.value = routine.horario || ""
    if (activitiesInput) activitiesInput.value = Array.isArray(routine.actividades) ? routine.actividades.join("\n") : ""
    if (title) title.textContent = "Editar rutina"
    if (saveButton) saveButton.textContent = "Guardar cambios"
    if (cancelButton) cancelButton.hidden = false
    setCustomRoutineMessage("")
    document.getElementById("professionalCustomRoutineForm")?.scrollIntoView({ behavior: "smooth", block: "start" })
  }

  async function deleteCustomRoutine(routineId) {
    const confirmed = await showProfessionalConfirm("Deseas eliminar esta rutina?", {
      title: "Eliminar rutina",
      confirmText: "Eliminar",
      cancelText: "Cancelar",
      variant: "danger",
    })
    if (!confirmed) return

    try {
      await routineService.deleteRoutine(routineId)

      if (String(editingCustomRoutineId) === String(routineId)) {
        resetCustomRoutineForm()
      }

      const message = "Rutina eliminada correctamente."
      setCustomRoutineMessage(message)
      await loadRoutinesAndNotes()
      await showProfessionalAlert(message, {
        title: "Rutina eliminada",
        variant: "success",
      })
    } catch (error) {
      const message = firstValidationMessage(error)
      setCustomRoutineMessage(message, true)
      await showProfessionalAlert(message, {
        title: "No se pudo eliminar",
        variant: "error",
      })
    }
  }

  async function completeCustomRoutineActivity(routineId, activityIndex) {
    try {
      await routineService.completeActivity(routineId, activityIndex)

      setCustomRoutineMessage("Actividad marcada como completada.")
      await loadRoutinesAndNotes()
    } catch (error) {
      const message = firstValidationMessage(error)
      setCustomRoutineMessage(message, true)
      await showProfessionalAlert(message, {
        title: "No se pudo completar",
        variant: "error",
      })
    }
  }

  async function saveNote() {
    if (!activeOlderAdultId) {
      setMessage("Selecciona un adulto mayor antes de guardar una nota.", true)
      return
    }

    const textarea = document.getElementById("routineNoteInput")
    const saveButton = document.getElementById("saveRoutineNoteButton")
    const content = textarea?.value.trim() || ""

    if (!content) {
      setMessage("La nota no puede estar vacia.", true)
      return
    }

    try {
      if (saveButton) {
        saveButton.disabled = true
        saveButton.textContent = editingNoteId ? "Guardando cambios..." : "Guardando..."
      }

      const wasEditing = Boolean(editingNoteId)
      await routineService.saveNote({
        id: editingNoteId,
        olderAdultId: activeOlderAdultId,
        content,
      })

      const successMessage = wasEditing ? "Nota actualizada correctamente." : "Nota guardada correctamente."
      setMessage(successMessage)
      resetNoteForm()
      await loadRoutinesAndNotes()
      await showProfessionalAlert(successMessage, {
        title: "Nota guardada",
        variant: "success",
      })
    } catch (error) {
      setMessage(error.message, true)
      await showProfessionalAlert(error.message, {
        title: "No se pudo guardar",
        variant: "error",
      })
    } finally {
      if (saveButton) {
        saveButton.disabled = false
        saveButton.textContent = "Guardar nota"
      }
    }
  }

  function startEditNote(noteId) {
    const notesList = document.getElementById("professionalRoutineNotesList")
    const noteCard = notesList?.querySelector(`[data-note-id="${noteId}"] p`)
    const textarea = document.getElementById("routineNoteInput")
    const title = document.getElementById("routineNoteFormTitle")
    const saveButton = document.getElementById("saveRoutineNoteButton")
    const cancelButton = document.getElementById("cancelRoutineNoteEdit")

    if (!noteCard || !textarea) return

    editingNoteId = noteId
    textarea.value = noteCard.textContent.trim()
    textarea.focus()
    if (title) title.textContent = "Editar nota"
    if (saveButton) saveButton.textContent = "Guardar cambios"
    if (cancelButton) cancelButton.hidden = false
    setMessage("")
  }

  async function deleteNote(noteId) {
    const confirmed = await showProfessionalConfirm("Deseas eliminar esta nota?", {
      title: "Eliminar nota",
      confirmText: "Eliminar",
      cancelText: "Cancelar",
      variant: "danger",
    })
    if (!confirmed) return

    try {
      await routineService.deleteNote(noteId)

      if (String(editingNoteId) === String(noteId)) {
        resetNoteForm()
      }

      const message = "Nota eliminada correctamente."
      setMessage(message)
      await loadRoutinesAndNotes()
      await showProfessionalAlert(message, {
        title: "Nota eliminada",
        variant: "success",
      })
    } catch (error) {
      setMessage(error.message, true)
      await showProfessionalAlert(error.message, {
        title: "No se pudo eliminar",
        variant: "error",
      })
    }
  }

  async function initialize() {
    try {
      await loadAdults()

      if (!assignedAdults.length) {
        renderAdultSelector()
        renderEmptyState("No tienes adultos mayores asignados por ahora.")
        return
      }

      const requestedAdultId = getRequestedAdultId()
      const hasRequestedAdult = assignedAdults.some((adult) => String(adult.id) === String(requestedAdultId))
      activeOlderAdultId = hasRequestedAdult ? requestedAdultId : String(assignedAdults[0].id)

      renderAdultSelector()
      await loadRoutinesAndNotes()
    } catch (error) {
      renderEmptyState(error.message)
    }
  }

  window.ProfessionalRoutinesEvents.bind({
    async changeAdult(adultId) {
      activeOlderAdultId = adultId
      resetNoteForm()
      resetCustomRoutineForm()
      setMessage("")
      setCustomRoutineMessage("")
      await loadRoutinesAndNotes()
    },
    saveCustomRoutine,
    cancelCustomRoutine() {
      resetCustomRoutineForm()
      setCustomRoutineMessage("")
    },
    saveNote,
    cancelNote() {
      resetNoteForm()
      setMessage("")
    },
    editNote: startEditNote,
    deleteNote,
    editCustomRoutine: startEditCustomRoutine,
    deleteCustomRoutine,
    completeActivity: completeCustomRoutineActivity,
    initialize,
  })
})()
