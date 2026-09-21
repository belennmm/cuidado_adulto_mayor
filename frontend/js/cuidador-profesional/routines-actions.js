(() => {
  function create({ state, routineService, parseActivities, isValidSchedule, setCustomRoutineMessage, setMessage, firstValidationMessage, resetCustomRoutineForm, resetNoteForm, loadRoutinesAndNotes, showProfessionalAlert, showProfessionalConfirm }) {
    async function saveCustomRoutine() {
      if (!state.activeOlderAdultId) {
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
          id: state.editingCustomRoutineId,
          olderAdultId: state.activeOlderAdultId,
          nombre,
          horario,
          actividades,
        })

        const wasEditing = Boolean(state.editingCustomRoutineId)
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
          saveButton.textContent = state.editingCustomRoutineId ? "Guardar cambios" : "Guardar rutina"
        }
      }
    }

    function startEditCustomRoutine(routineId) {
      const routine = state.currentCustomRoutines.find((item) => String(item.id) === String(routineId))
      if (!routine) return

      const nameInput = document.getElementById("customRoutineName")
      const scheduleInput = document.getElementById("customRoutineSchedule")
      const activitiesInput = document.getElementById("customRoutineActivities")
      const title = document.getElementById("customRoutineFormTitle")
      const saveButton = document.getElementById("saveCustomRoutineButton")
      const cancelButton = document.getElementById("cancelCustomRoutineEdit")

      state.editingCustomRoutineId = routine.id
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

        if (String(state.editingCustomRoutineId) === String(routineId)) {
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
      if (!state.activeOlderAdultId) {
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
          saveButton.textContent = state.editingNoteId ? "Guardando cambios..." : "Guardando..."
        }

        const wasEditing = Boolean(state.editingNoteId)
        await routineService.saveNote({
          id: state.editingNoteId,
          olderAdultId: state.activeOlderAdultId,
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

      state.editingNoteId = noteId
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

        if (String(state.editingNoteId) === String(noteId)) {
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

    return Object.freeze({ saveCustomRoutine, startEditCustomRoutine, deleteCustomRoutine, completeCustomRoutineActivity, saveNote, startEditNote, deleteNote })
  }
  window.ProfessionalRoutinesActions = Object.freeze({ create })
})()

