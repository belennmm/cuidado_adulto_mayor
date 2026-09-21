(() => {
  function create({ state, escapeHtml, setText }) {
    function renderAdultSelector() {
      const selector = document.getElementById("adminRoutineAdultSelector")
      if (!selector) return

      selector.innerHTML = state.olderAdults
        .map((adult) => `
          <option value="${escapeHtml(adult.id)}">
            ${escapeHtml(adult.full_name || "Adulto mayor")}
          </option>
        `)
        .join("")

      if (state.activeOlderAdultId) selector.value = String(state.activeOlderAdultId)
    }

    function resetForm() {
      state.editingRoutineId = null
      const nameInput = document.getElementById("adminRoutineName")
      const scheduleInput = document.getElementById("adminRoutineSchedule")
      const activitiesInput = document.getElementById("adminRoutineActivities")
      const title = document.getElementById("adminRoutineFormTitle")
      const saveButton = document.getElementById("adminSaveRoutineButton")
      const cancelButton = document.getElementById("adminCancelRoutineEdit")

      if (nameInput) nameInput.value = ""
      if (scheduleInput) scheduleInput.value = ""
      if (activitiesInput) activitiesInput.value = ""
      if (title) title.textContent = "Crear rutina"
      if (saveButton) saveButton.textContent = "Guardar rutina"
      if (cancelButton) cancelButton.hidden = true
    }

    function parseActivities(value) {
      return String(value || "")
        .split(/\r?\n|,/)
        .map((activity) => activity.trim())
        .filter(Boolean)
    }

    function renderEmpty(targetId, message) {
      const target = document.getElementById(targetId)
      if (!target) return
      target.innerHTML = `<div class="empty-state">${escapeHtml(message)}</div>`
    }

    function renderRoutines(routines) {
      const list = document.getElementById("adminRoutinesList")
      if (!list) return

      state.currentRoutines = routines
      setText("adminRoutineTotal", routines.length)

      if (!routines.length) {
        renderEmpty("adminRoutinesList", "No hay rutinas creadas para este adulto mayor.")
        return
      }

      list.innerHTML = routines.map((routine) => {
        const activities = Array.isArray(routine.actividades) ? routine.actividades : []

        return `
          <article class="routine-card">
            <div class="routine-card-top">
              <div>
                <strong>${escapeHtml(routine.nombre || "Rutina")}</strong>
                <span>${escapeHtml(routine.horario || "Sin horario")}</span>
              </div>
              <div class="routine-card-actions">
                <span class="badge">${activities.length} actividades</span>
                <button type="button" class="text-button" data-routine-action="edit" data-id="${escapeHtml(routine.id)}">Editar</button>
                <button type="button" class="text-button is-danger" data-routine-action="delete" data-id="${escapeHtml(routine.id)}">Eliminar</button>
              </div>
            </div>
            <ul>
              ${activities.map((activity) => `<li>${escapeHtml(activity)}</li>`).join("")}
            </ul>
          </article>
        `
      }).join("")
    }

    function renderMedications(adult) {
      const medications = adult?.medications || []
      const list = document.getElementById("adminMedicationsList")

      setText("adminMedicationTotal", medications.length)
      setText("adminMedicationMeta", medications.length ? "Asignados en el perfil clínico" : "Sin medicamentos asignados")

      if (!list) return

      if (!medications.length) {
        renderEmpty("adminMedicationsList", "Este adulto mayor no tiene medicamentos asignados.")
        return
      }

      list.innerHTML = medications.map((medication) => `
        <article class="routine-card">
          <div class="routine-card-top">
            <div>
              <strong>${escapeHtml(medication.name || "Medicamento")}</strong>
              <p>${escapeHtml(medication.dosage || "Sin dosis")} &middot; ${escapeHtml(medication.schedule || "Sin horario")}</p>
            </div>
            <span class="badge">${escapeHtml((medication.days || []).length ? medication.days.join(", ") : "Diario")}</span>
          </div>
          <p>${escapeHtml(medication.notes || "Sin notas.")}</p>
        </article>
      `).join("")
    }

    function renderMeta(adult) {
      setText(
        "adminRoutineAdultMeta",
        adult ? `${adult.full_name || "Adulto mayor"}${adult.room ? ` - Habitacion ${adult.room}` : ""}` : "Sin adulto mayor seleccionado"
      )
    }

    return Object.freeze({ renderAdultSelector, resetForm, parseActivities, renderEmpty, renderRoutines, renderMedications, renderMeta })
  }
  window.AdminRoutinesView = Object.freeze({ create })
})()

