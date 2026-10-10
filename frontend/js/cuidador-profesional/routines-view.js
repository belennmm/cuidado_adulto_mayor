(() => {
  const api = () => window.ProfessionalCare
  const setText = (...args) => window.CuidadoUi.setText(...args)

  function renderAdultSelector(adults, activeId) {
    const selector = document.getElementById("professionalRoutineAdultSelector")
    if (!selector) return
    selector.replaceChildren(...adults.map((adult) => {
      const option = window.CuidadoUi.element("option", "", adult.full_name || "Adulto mayor")
      option.value = String(adult.id ?? "")
      return option
    }))
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

  function routineButton(label, action, id, index = null) {
    const button = window.CuidadoUi.element("button", `routine-note-text-button${action === "delete" ? " is-danger" : ""}`, label)
    button.type = "button"
    button.dataset.customRoutineAction = action
    button.dataset.id = String(id ?? "")
    if (index !== null) button.dataset.activityIndex = String(index)
    return button
  }

  function renderCustomRoutine(routine) {
    const el = window.CuidadoUi.element
    const activities = Array.isArray(routine.actividades) ? routine.actividades : []
    const completedActivities = routine.actividades_completadas || {}
    const isCompleted = Boolean(routine.completada)
    const completedAt = formatCompletedAt(routine.completada_at)
    const actions = el("div", "routine-note-card-actions", null, [
      el("span", "badge badge-blue", `${activities.length} actividades`),
      isCompleted ? el("span", "badge badge-success", "Rutina completada") : null,
      routineButton("Editar", "edit", routine.id), routineButton("Eliminar", "delete", routine.id),
    ])
    const items = activities.map((activity, index) => {
      const completed = completedActivities[index]
      const done = Boolean(completed?.completada)
      const at = formatCompletedAt(completed?.completada_at)
      return el("li", done ? "is-completed" : "", null, [
        el("span", "", null, [el("strong", "", activity), done && at ? el("small", "", `Completada ${at}`) : null]),
        done ? el("span", "badge badge-success", "Completada") : routineButton("Completar", "complete", routine.id, index),
      ])
    })
    return el("article", `routine-note-card custom-routine-card${isCompleted ? " is-completed" : ""}`, null, [
      el("div", "routine-note-card-top", null, [el("div", "", null, [
        el("strong", "", routine.nombre || "Rutina"),
        el("span", "", `${routine.horario || "Sin horario"}${isCompleted && completedAt ? ` · Completada ${completedAt}` : ""}`),
      ]), actions]), el("ul", "custom-routine-activity-list", null, items),
    ])
  }

  function renderCustomRoutines(routines) {
    const list = document.getElementById("professionalCustomRoutinesList")
    if (!list) return
    setText("professionalCustomRoutineTotal", routines.length)
    list.replaceChildren(...(routines.length ? routines.map(renderCustomRoutine) : [window.CuidadoUi.element("div", "empty-state", "No hay rutinas creadas para este adulto mayor.")]))
  }

  function renderNotes(notes) {
    const list = document.getElementById("professionalRoutineNotesList")
    if (!list) return
    const el = window.CuidadoUi.element
    setText("professionalWeeklyNotesCount", notes.length)
    list.replaceChildren()
    if (!notes.length) {
      list.append(el("div", "empty-state", "No hay notas registradas para esta semana y este adulto mayor."))
      return
    }
    for (const note of notes) {
      const actions = el("div", "routine-note-card-actions")
      for (const [action, label] of [["edit", "Editar"], ["delete", "Eliminar"]]) {
        const button = el("button", `routine-note-text-button${action === "delete" ? " is-danger" : ""}`, label)
        button.type = "button"
        button.dataset.action = action
        button.dataset.id = String(note.id ?? "")
        actions.append(button)
      }
      const card = el("article", "routine-note-card", null, [
        el("div", "routine-note-card-top", null, [el("div", "", null, [
          el("strong", "", api().formatShortDate(note.note_date)), el("span", "", note.professional_caregiver?.name || "Cuidador profesional"),
        ]), actions]), el("p", "", note.content),
      ])
      card.dataset.noteId = String(note.id ?? "")
      list.append(card)
    }
  }

  function renderEmpty(message) {
    ;["professionalRoutinesList", "professionalRoutineNotesList", "professionalCustomRoutinesList"].forEach((id) => {
      const list = document.getElementById(id)
      if (list) list.replaceChildren(window.CuidadoUi.element("div", "empty-state", message))
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
