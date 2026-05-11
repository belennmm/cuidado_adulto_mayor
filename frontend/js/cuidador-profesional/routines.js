(() => {
  const api = window.ProfessionalCare
  let assignedAdults = []
  let activeOlderAdultId = ""
  let editingNoteId = null
  let editingCustomRoutineId = null
  let currentCustomRoutines = []

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

  function setText(id, value, fallback = "0") {
    const element = document.getElementById(id)
    if (!element) return
    element.textContent = value ?? fallback
  }

  function setMessage(message, isError = false) {
    const element = document.getElementById("routineNoteMessage")
    if (!element) return

    element.textContent = message || ""
    element.classList.toggle("is-error", isError)
    element.classList.toggle("is-success", Boolean(message) && !isError)
  }

  function setCustomRoutineMessage(message, isError = false) {
    const element = document.getElementById("customRoutineMessage")
    if (!element) return

    element.textContent = message || ""
    element.classList.toggle("is-error", isError)
    element.classList.toggle("is-success", Boolean(message) && !isError)
  }

  function firstValidationMessage(error) {
    const errors = error?.errors || {}
    const firstField = Object.keys(errors)[0]

    if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
      return errors[firstField][0]
    }

    return error?.message || "No se pudo guardar la rutina."
  }

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
    const textarea = document.getElementById("routineNoteInput")
    const title = document.getElementById("routineNoteFormTitle")
    const saveButton = document.getElementById("saveRoutineNoteButton")
    const cancelButton = document.getElementById("cancelRoutineNoteEdit")

    if (textarea) textarea.value = ""
    if (title) title.textContent = "Agregar nota"
    if (saveButton) saveButton.textContent = "Guardar nota"
    if (cancelButton) cancelButton.hidden = true
  }

  function resetCustomRoutineForm() {
    editingCustomRoutineId = null
    const nameInput = document.getElementById("customRoutineName")
    const scheduleInput = document.getElementById("customRoutineSchedule")
    const activitiesInput = document.getElementById("customRoutineActivities")
    const title = document.getElementById("customRoutineFormTitle")
    const saveButton = document.getElementById("saveCustomRoutineButton")
    const cancelButton = document.getElementById("cancelCustomRoutineEdit")

    if (nameInput) nameInput.value = ""
    if (scheduleInput) scheduleInput.value = ""
    if (activitiesInput) activitiesInput.value = ""
    if (title) title.textContent = "Crear rutina"
    if (saveButton) saveButton.textContent = "Guardar rutina"
    if (cancelButton) cancelButton.hidden = true
  }

  function renderAdultSelector() {
    const selector = document.getElementById("professionalRoutineAdultSelector")
    if (!selector) return

    selector.innerHTML = assignedAdults
      .map((adult) => `
        <option value="${api.escapeHtml(adult.id)}">
          ${api.escapeHtml(adult.full_name || "Adulto mayor")}
        </option>
      `)
      .join("")

    if (activeOlderAdultId) {
      selector.value = String(activeOlderAdultId)
    }
  }

  function statusLabel(item) {
    if (item.administered_today) return "Administrado"
    if (item.due_today) return "Pendiente"
    return "Programado"
  }

  function renderMedicineRoutine(item) {
    return `
      <article class="professional-row">
        <span class="row-icon"><i class="bx bxs-capsule"></i></span>
        <div>
          <h3>${api.escapeHtml(item.medication_name || "Medicamento")}</h3>
          <p>${api.escapeHtml(item.older_adult_name || "Adulto mayor")} &middot; ${api.escapeHtml(item.schedule || "Sin horario")} &middot; ${api.escapeHtml(item.dosage || "Sin dosis")}</p>
        </div>
        <span class="badge ${item.administered_today ? "badge-success" : item.due_today ? "badge-warning" : "badge-blue"}">
          ${api.escapeHtml(statusLabel(item))}
        </span>
      </article>
    `
  }

  function renderCustomRoutine(routine) {
    const activities = Array.isArray(routine.actividades) ? routine.actividades : []
    const completedActivities = routine.actividades_completadas || {}

    return `
      <article class="routine-note-card custom-routine-card">
        <div class="routine-note-card-top">
          <div>
            <strong>${api.escapeHtml(routine.nombre || "Rutina")}</strong>
            <span>${api.escapeHtml(routine.horario || "Sin horario")}</span>
          </div>
          <div class="routine-note-card-actions">
            <span class="badge badge-blue">${activities.length} actividades</span>
            <button type="button" class="routine-note-text-button" data-custom-routine-action="edit" data-id="${routine.id}">Editar</button>
            <button type="button" class="routine-note-text-button is-danger" data-custom-routine-action="delete" data-id="${routine.id}">Eliminar</button>
          </div>
        </div>
        <ul class="custom-routine-activity-list">
          ${activities.map((activity, index) => {
            const completedActivity = completedActivities[index] || completedActivities[String(index)]
            const isCompleted = Boolean(completedActivity?.completada)

            return `
              <li class="${isCompleted ? "is-completed" : ""}">
                <span>${api.escapeHtml(activity)}</span>
                ${isCompleted
                  ? `<span class="badge badge-success">Completada</span>`
                  : `<button type="button" class="routine-note-text-button" data-custom-routine-action="complete" data-id="${routine.id}" data-activity-index="${index}">Completar</button>`
                }
              </li>
            `
          }).join("")}
        </ul>
      </article>
    `
  }

  function renderCustomRoutines(routines) {
    const list = document.getElementById("professionalCustomRoutinesList")
    if (!list) return

    currentCustomRoutines = routines
    setText("professionalCustomRoutineTotal", routines.length)

    if (!routines.length) {
      list.innerHTML = api.renderEmpty("No hay rutinas creadas para este adulto mayor.")
      return
    }

    list.innerHTML = routines.map(renderCustomRoutine).join("")
  }

  function renderNotes(notes) {
    const list = document.getElementById("professionalRoutineNotesList")
    if (!list) return

    setText("professionalWeeklyNotesCount", notes.length)

    if (!notes.length) {
      list.innerHTML = api.renderEmpty("No hay notas registradas para esta semana y este adulto mayor.")
      return
    }

    list.innerHTML = notes.map((note) => `
      <article class="routine-note-card" data-note-id="${note.id}">
        <div class="routine-note-card-top">
          <div>
            <strong>${api.formatShortDate(note.note_date)}</strong>
            <span>${api.escapeHtml(note.professional_caregiver?.name || "Cuidador profesional")}</span>
          </div>
          <div class="routine-note-card-actions">
            <button type="button" class="routine-note-text-button" data-action="edit" data-id="${note.id}">Editar</button>
            <button type="button" class="routine-note-text-button is-danger" data-action="delete" data-id="${note.id}">Eliminar</button>
          </div>
        </div>
        <p>${api.escapeHtml(note.content)}</p>
      </article>
    `).join("")
  }

  function renderEmptyState(message) {
    const routinesList = document.getElementById("professionalRoutinesList")
    const notesList = document.getElementById("professionalRoutineNotesList")

    if (routinesList) {
      routinesList.innerHTML = api.renderEmpty(message)
    }

    if (notesList) {
      notesList.innerHTML = api.renderEmpty(message)
    }

    setText("professionalRoutineTotal", 0)
    setText("professionalRoutinePending", 0)
    setText("professionalRoutineAdministered", 0)
    setText("professionalWeeklyNotesCount", 0)
    setText("professionalCustomRoutineTotal", 0)
    setText("professionalRoutineWeekRange", "Sin semana activa")
    setText("professionalRoutineAdultMeta", "Sin adulto mayor seleccionado")

    const customRoutinesList = document.getElementById("professionalCustomRoutinesList")
    if (customRoutinesList) {
      customRoutinesList.innerHTML = api.renderEmpty(message)
    }
  }

  function renderSummary(routineData, olderAdult) {
    setText("professionalRoutineTotal", routineData.summary?.total ?? 0)
    setText("professionalRoutinePending", routineData.summary?.pending_today ?? 0)
    setText("professionalRoutineAdministered", routineData.summary?.administered_today ?? 0)
    setText("professionalRoutineWeekRange", "Semana actual")
    setText(
      "professionalRoutineAdultMeta",
      `${olderAdult?.full_name || "Adulto mayor"}${olderAdult?.room ? ` · Habitacion ${olderAdult.room}` : ""}`
    )
  }

  function renderWeekRange(week) {
    const start = api.formatShortDate(week?.start)
    const end = api.formatShortDate(week?.end)
    setText("professionalRoutineWeekRange", `${start} - ${end}`)
  }

  async function loadAdults() {
    const data = await api.fetchJson("/professional/older-adults")
    assignedAdults = data.older_adults || []
  }

  async function loadRoutinesAndNotes() {
    if (!activeOlderAdultId) {
      renderEmptyState("No tienes adultos mayores asignados por ahora.")
      return
    }

    try {
      const selectedAdult = assignedAdults.find((adult) => String(adult.id) === String(activeOlderAdultId))
      const [routineData, notesData, customRoutineData] = await Promise.all([
        api.fetchJson(`/professional/routines?older_adult_id=${encodeURIComponent(activeOlderAdultId)}`),
        api.fetchJson(`/professional/routine-notes?older_adult_id=${encodeURIComponent(activeOlderAdultId)}`),
        api.fetchJson(`/rutinas?older_adult_id=${encodeURIComponent(activeOlderAdultId)}`),
      ])

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

  function parseActivities(value) {
    return String(value || "")
      .split(/\r?\n|,/)
      .map((activity) => activity.trim())
      .filter(Boolean)
  }

  function isValidSchedule(value) {
    if (!/^\d{2}:\d{2}$/.test(value)) return false

    const [hours, minutes] = value.split(":").map(Number)
    return hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59
  }

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

      await api.fetchJson(editingCustomRoutineId ? `/rutinas/${editingCustomRoutineId}` : "/rutinas", {
        method: editingCustomRoutineId ? "PUT" : "POST",
        body: JSON.stringify({
          nombre,
          horario,
          actividades,
          ...(editingCustomRoutineId ? {} : { adulto_mayor_id: activeOlderAdultId }),
        }),
      })

      const wasEditing = Boolean(editingCustomRoutineId)
      resetCustomRoutineForm()
      const successMessage = wasEditing ? "Rutina actualizada correctamente." : "Rutina creada correctamente."
      setCustomRoutineMessage(successMessage)
      await loadRoutinesAndNotes()
      await showProfessionalAlert(successMessage, {
        title: "Rutina guardada",
        variant: "info",
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
      await api.fetchJson(`/rutinas/${routineId}`, {
        method: "DELETE",
      })

      if (String(editingCustomRoutineId) === String(routineId)) {
        resetCustomRoutineForm()
      }

      const message = "Rutina eliminada correctamente."
      setCustomRoutineMessage(message)
      await loadRoutinesAndNotes()
      await showProfessionalAlert(message, {
        title: "Rutina eliminada",
        variant: "info",
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
      await api.fetchJson(`/rutinas/${routineId}/completar`, {
        method: "PATCH",
        body: JSON.stringify({
          actividad_index: Number(activityIndex),
        }),
      })

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
      const path = editingNoteId
        ? `/professional/routine-notes/${editingNoteId}`
        : "/professional/routine-notes"

      const method = editingNoteId ? "PUT" : "POST"
      const body = editingNoteId
        ? { content }
        : { older_adult_id: activeOlderAdultId, content }

      await api.fetchJson(path, {
        method,
        body: JSON.stringify(body),
      })

      const successMessage = wasEditing ? "Nota actualizada correctamente." : "Nota guardada correctamente."
      setMessage(successMessage)
      resetNoteForm()
      await loadRoutinesAndNotes()
      await showProfessionalAlert(successMessage, {
        title: "Nota guardada",
        variant: "info",
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
      await api.fetchJson(`/professional/routine-notes/${noteId}`, {
        method: "DELETE",
      })

      if (String(editingNoteId) === String(noteId)) {
        resetNoteForm()
      }

      const message = "Nota eliminada correctamente."
      setMessage(message)
      await loadRoutinesAndNotes()
      await showProfessionalAlert(message, {
        title: "Nota eliminada",
        variant: "info",
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

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("professionalRoutineAdultSelector")?.addEventListener("change", async (event) => {
      activeOlderAdultId = event.target.value
      resetNoteForm()
      resetCustomRoutineForm()
      setMessage("")
      setCustomRoutineMessage("")
      await loadRoutinesAndNotes()
    })

    document.getElementById("professionalCustomRoutineForm")?.addEventListener("submit", async (event) => {
      event.preventDefault()
      await saveCustomRoutine()
    })

    document.getElementById("cancelCustomRoutineEdit")?.addEventListener("click", () => {
      resetCustomRoutineForm()
      setCustomRoutineMessage("")
    })

    document.getElementById("professionalRoutineNoteForm")?.addEventListener("submit", async (event) => {
      event.preventDefault()
      await saveNote()
    })

    document.getElementById("cancelRoutineNoteEdit")?.addEventListener("click", () => {
      resetNoteForm()
      setMessage("")
    })

    document.getElementById("professionalRoutineNotesList")?.addEventListener("click", async (event) => {
      const button = event.target.closest("button[data-action][data-id]")
      if (!button) return

      if (button.dataset.action === "edit") {
        startEditNote(button.dataset.id)
        return
      }

      if (button.dataset.action === "delete") {
        await deleteNote(button.dataset.id)
      }
    })

    document.getElementById("professionalCustomRoutinesList")?.addEventListener("click", async (event) => {
      const button = event.target.closest("button[data-custom-routine-action][data-id]")
      if (!button) return

      if (button.dataset.customRoutineAction === "edit") {
        startEditCustomRoutine(button.dataset.id)
        return
      }

      if (button.dataset.customRoutineAction === "delete") {
        await deleteCustomRoutine(button.dataset.id)
        return
      }

      if (button.dataset.customRoutineAction === "complete") {
        await completeCustomRoutineActivity(button.dataset.id, button.dataset.activityIndex)
      }
    })

    initialize()
  })
})()
