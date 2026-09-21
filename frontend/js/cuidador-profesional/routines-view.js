(() => {
  const api = () => window.ProfessionalCare
  const setText = (...args) => window.CuidadoUi.setText(...args)

  function renderAdultSelector(adults, activeId) {
    const selector = document.getElementById("professionalRoutineAdultSelector")
    if (!selector) return
    selector.innerHTML = adults.map((adult) => `
      <option value="${api().escapeHtml(adult.id)}">${api().escapeHtml(adult.full_name || "Adulto mayor")}</option>
    `).join("")
    if (activeId) selector.value = String(activeId)
  }

  function medicineStatus(item) {
    if (item.administered_today) return "Administrado"
    if (item.due_today) return "Pendiente"
    return "Programado"
  }

  function renderMedicine(item) {
    return `
      <article class="professional-row">
        <span class="row-icon"><i class="bx bxs-capsule"></i></span>
        <div>
          <h3>${api().escapeHtml(item.medication_name || "Medicamento")}</h3>
          <p>${api().escapeHtml(item.older_adult_name || "Adulto mayor")} &middot; ${api().escapeHtml(item.schedule || "Sin horario")} &middot; ${api().escapeHtml(item.dosage || "Sin dosis")}</p>
        </div>
        <span class="badge ${item.administered_today ? "badge-success" : item.due_today ? "badge-warning" : "badge-blue"}">${api().escapeHtml(medicineStatus(item))}</span>
      </article>`
  }

  function formatCompletedAt(value) {
    if (!value) return ""
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ""
    return new Intl.DateTimeFormat("es-GT", {
      day: "numeric", month: "short", hour: "2-digit", minute: "2-digit",
    }).format(date)
  }

  function renderCustomRoutine(routine) {
    const activities = Array.isArray(routine.actividades) ? routine.actividades : []
    const completedActivities = routine.actividades_completadas || {}
    const isCompleted = Boolean(routine.completada)
    const completedAt = formatCompletedAt(routine.completada_at)
    return `
      <article class="routine-note-card custom-routine-card ${isCompleted ? "is-completed" : ""}">
        <div class="routine-note-card-top">
          <div><strong>${api().escapeHtml(routine.nombre || "Rutina")}</strong><span>${api().escapeHtml(routine.horario || "Sin horario")}${isCompleted && completedAt ? ` &middot; Completada ${api().escapeHtml(completedAt)}` : ""}</span></div>
          <div class="routine-note-card-actions">
            <span class="badge badge-blue">${activities.length} actividades</span>
            ${isCompleted ? '<span class="badge badge-success">Rutina completada</span>' : ""}
            <button type="button" class="routine-note-text-button" data-custom-routine-action="edit" data-id="${routine.id}">Editar</button>
            <button type="button" class="routine-note-text-button is-danger" data-custom-routine-action="delete" data-id="${routine.id}">Eliminar</button>
          </div>
        </div>
        <ul class="custom-routine-activity-list">${activities.map((activity, index) => {
          const completed = completedActivities[index] || completedActivities[String(index)]
          const activityCompleted = Boolean(completed?.completada)
          const activityCompletedAt = formatCompletedAt(completed?.completada_at)
          return `<li class="${activityCompleted ? "is-completed" : ""}"><span><strong>${api().escapeHtml(activity)}</strong>${activityCompleted && activityCompletedAt ? `<small>Completada ${api().escapeHtml(activityCompletedAt)}</small>` : ""}</span>${activityCompleted ? '<span class="badge badge-success">Completada</span>' : `<button type="button" class="routine-note-text-button" data-custom-routine-action="complete" data-id="${routine.id}" data-activity-index="${index}">Completar</button>`}</li>`
        }).join("")}</ul>
      </article>`
  }

  function renderCustomRoutines(routines) {
    const list = document.getElementById("professionalCustomRoutinesList")
    if (!list) return
    setText("professionalCustomRoutineTotal", routines.length)
    list.innerHTML = routines.length
      ? routines.map(renderCustomRoutine).join("")
      : api().renderEmpty("No hay rutinas creadas para este adulto mayor.")
  }

  function renderNotes(notes) {
    const list = document.getElementById("professionalRoutineNotesList")
    if (!list) return
    setText("professionalWeeklyNotesCount", notes.length)
    if (!notes.length) {
      list.innerHTML = api().renderEmpty("No hay notas registradas para esta semana y este adulto mayor.")
      return
    }
    list.innerHTML = notes.map((note) => `
      <article class="routine-note-card" data-note-id="${note.id}">
        <div class="routine-note-card-top"><div><strong>${api().formatShortDate(note.note_date)}</strong><span>${api().escapeHtml(note.professional_caregiver?.name || "Cuidador profesional")}</span></div>
          <div class="routine-note-card-actions"><button type="button" class="routine-note-text-button" data-action="edit" data-id="${note.id}">Editar</button><button type="button" class="routine-note-text-button is-danger" data-action="delete" data-id="${note.id}">Eliminar</button></div>
        </div><p>${api().escapeHtml(note.content)}</p>
      </article>`).join("")
  }

  function renderEmpty(message) {
    ;["professionalRoutinesList", "professionalRoutineNotesList", "professionalCustomRoutinesList"].forEach((id) => {
      const list = document.getElementById(id)
      if (list) list.innerHTML = api().renderEmpty(message)
    })
    ;["professionalRoutineTotal", "professionalRoutinePending", "professionalRoutineAdministered", "professionalWeeklyNotesCount", "professionalCustomRoutineTotal"].forEach((id) => setText(id, 0))
    setText("professionalRoutineWeekRange", "Sin semana activa")
    setText("professionalRoutineAdultMeta", "Sin adulto mayor seleccionado")
  }

  function renderSummary(data, adult) {
    setText("professionalRoutineTotal", data.summary?.total ?? 0)
    setText("professionalRoutinePending", data.summary?.pending_today ?? 0)
    setText("professionalRoutineAdministered", data.summary?.administered_today ?? 0)
    setText("professionalRoutineWeekRange", "Semana actual")
    setText("professionalRoutineAdultMeta", `${adult?.full_name || "Adulto mayor"}${adult?.room ? ` · Habitacion ${adult.room}` : ""}`)
  }

  function renderWeekRange(week) {
    setText("professionalRoutineWeekRange", `${api().formatShortDate(week?.start)} - ${api().formatShortDate(week?.end)}`)
  }

  window.ProfessionalRoutinesView = Object.freeze({
    renderAdultSelector, renderCustomRoutines, renderEmpty, renderMedicine,
    renderNotes, renderSummary, renderWeekRange,
  })
})()
