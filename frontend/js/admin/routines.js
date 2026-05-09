(() => {
  const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

  let olderAdults = []
  let activeOlderAdultId = ""
  let currentRoutines = []
  let editingRoutineId = null

  function getToken() {
    return localStorage.getItem("token")
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;")
  }

  function setText(id, value) {
    const element = document.getElementById(id)
    if (element) element.textContent = value ?? ""
  }

  function setMessage(message, isError = false) {
    const element = document.getElementById("adminRoutineMessage")
    if (!element) return

    element.textContent = message || ""
    element.classList.toggle("is-error", isError)
  }

  async function fetchJson(path, options = {}) {
    const token = getToken()

    if (!token) {
      throw new Error("Inicia sesion como administrador para gestionar rutinas.")
    }

    const response = await fetch(`${API_URL}${path}`, {
      cache: "no-store",
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        ...(options.headers || {}),
      },
    })

    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(firstValidationMessage(data))
    }

    return data
  }

  function firstValidationMessage(error) {
    const errors = error?.errors || {}
    const firstField = Object.keys(errors)[0]

    if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
      return errors[firstField][0]
    }

    return error?.message || "No se pudo completar la accion."
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

  function renderAdultSelector() {
    const selector = document.getElementById("adminRoutineAdultSelector")
    if (!selector) return

    selector.innerHTML = olderAdults
      .map((adult) => `
        <option value="${escapeHtml(adult.id)}">
          ${escapeHtml(adult.full_name || "Adulto mayor")}
        </option>
      `)
      .join("")

    if (activeOlderAdultId) selector.value = String(activeOlderAdultId)
  }

  function resetForm() {
    editingRoutineId = null
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

    currentRoutines = routines
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
    setText("adminMedicationMeta", medications.length ? "Asignados en el perfil clinico" : "Sin medicamentos asignados")

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

  async function loadAdults() {
    const data = await fetchJson("/admin/older-adults")
    olderAdults = data.older_adults || []
  }

  async function loadRoutines() {
    if (!activeOlderAdultId) {
      renderMeta(null)
      renderEmpty("adminRoutinesList", "Selecciona un adulto mayor para ver sus rutinas.")
      renderEmpty("adminMedicationsList", "Selecciona un adulto mayor para ver sus medicamentos.")
      setText("adminRoutineTotal", 0)
      setText("adminMedicationTotal", 0)
      return
    }

    const [routineData, adultData] = await Promise.all([
      fetchJson(`/rutinas?older_adult_id=${encodeURIComponent(activeOlderAdultId)}`),
      fetchJson(`/admin/older-adults/${encodeURIComponent(activeOlderAdultId)}`),
    ])
    const adult = adultData.older_adult || olderAdults.find((item) => String(item.id) === String(activeOlderAdultId))

    renderMeta(adult)
    renderMedications(adult)
    renderRoutines(routineData.rutinas || [])
    updateAdultUrl(activeOlderAdultId)
  }

  async function saveRoutine() {
    if (!activeOlderAdultId) {
      setMessage("Selecciona un adulto mayor antes de guardar una rutina.", true)
      return
    }

    const saveButton = document.getElementById("adminSaveRoutineButton")
    const nombre = document.getElementById("adminRoutineName")?.value.trim() || ""
    const horario = document.getElementById("adminRoutineSchedule")?.value.trim() || ""
    const actividades = parseActivities(document.getElementById("adminRoutineActivities")?.value)

    if (!nombre || !horario || !actividades.length) {
      setMessage("Completa nombre, horario y al menos una actividad.", true)
      return
    }

    try {
      if (saveButton) {
        saveButton.disabled = true
        saveButton.textContent = "Guardando..."
      }

      await fetchJson(editingRoutineId ? `/rutinas/${editingRoutineId}` : "/rutinas", {
        method: editingRoutineId ? "PUT" : "POST",
        body: JSON.stringify({
          nombre,
          horario,
          actividades,
          ...(editingRoutineId ? {} : { older_adult_id: activeOlderAdultId }),
        }),
      })

      const wasEditing = Boolean(editingRoutineId)
      resetForm()
      setMessage(wasEditing ? "Rutina actualizada correctamente." : "Rutina creada correctamente.")
      await loadRoutines()
    } catch (error) {
      setMessage(error.message, true)
    } finally {
      if (saveButton) {
        saveButton.disabled = false
        saveButton.textContent = editingRoutineId ? "Guardar cambios" : "Guardar rutina"
      }
    }
  }

  function startEditRoutine(routineId) {
    const routine = currentRoutines.find((item) => String(item.id) === String(routineId))
    if (!routine) return

    editingRoutineId = routine.id
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
    if (!confirm("Deseas eliminar esta rutina?")) return

    try {
      await fetchJson(`/rutinas/${routineId}`, { method: "DELETE" })

      if (String(editingRoutineId) === String(routineId)) resetForm()
      setMessage("Rutina eliminada correctamente.")
      await loadRoutines()
    } catch (error) {
      setMessage(error.message, true)
    }
  }

  async function initialize() {
    try {
      await loadAdults()

      if (!olderAdults.length) {
        renderAdultSelector()
        renderEmpty("adminRoutinesList", "No hay adultos mayores registrados.")
        renderEmpty("adminMedicationsList", "No hay adultos mayores registrados.")
        return
      }

      const requestedAdultId = getRequestedAdultId()
      const hasRequestedAdult = olderAdults.some((adult) => String(adult.id) === String(requestedAdultId))
      activeOlderAdultId = hasRequestedAdult ? requestedAdultId : String(olderAdults[0].id)

      renderAdultSelector()
      await loadRoutines()
    } catch (error) {
      renderEmpty("adminRoutinesList", error.message)
      renderEmpty("adminMedicationsList", error.message)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("adminRoutineAdultSelector")?.addEventListener("change", async (event) => {
      activeOlderAdultId = event.target.value
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
