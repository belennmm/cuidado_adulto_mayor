(() => {
  function firstValidationMessage(error) {
    const errors = error?.errors || {}
    const firstField = Object.keys(errors)[0]
    if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
      return errors[firstField][0]
    }
    return error?.message || "No se pudo guardar la rutina."
  }

  function resetNote() {
    const textarea = document.getElementById("routineNoteInput")
    const title = document.getElementById("routineNoteFormTitle")
    const saveButton = document.getElementById("saveRoutineNoteButton")
    const cancelButton = document.getElementById("cancelRoutineNoteEdit")
    if (textarea) textarea.value = ""
    if (title) title.textContent = "Agregar nota"
    if (saveButton) saveButton.textContent = "Guardar nota"
    if (cancelButton) cancelButton.hidden = true
  }

  function resetCustomRoutine() {
    const fields = ["customRoutineName", "customRoutineSchedule", "customRoutineActivities"]
    fields.forEach((id) => {
      const input = document.getElementById(id)
      if (input) input.value = ""
    })
    const title = document.getElementById("customRoutineFormTitle")
    const saveButton = document.getElementById("saveCustomRoutineButton")
    const cancelButton = document.getElementById("cancelCustomRoutineEdit")
    if (title) title.textContent = "Crear rutina"
    if (saveButton) saveButton.textContent = "Guardar rutina"
    if (cancelButton) cancelButton.hidden = true
  }

  function parseActivities(value) {
    return String(value || "").split(/\r?\n|,/).map((activity) => activity.trim()).filter(Boolean)
  }

  function isValidSchedule(value) {
    if (!/^\d{2}:\d{2}$/.test(value)) return false
    const [hours, minutes] = value.split(":").map(Number)
    return hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59
  }

  window.ProfessionalRoutineForm = Object.freeze({
    firstValidationMessage,
    isValidSchedule,
    parseActivities,
    resetCustomRoutine,
    resetNote,
  })
})()
