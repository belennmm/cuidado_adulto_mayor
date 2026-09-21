(() => {
  const api = window.ProfessionalCare
  const state = { assignedAdults: [], activeOlderAdultId: "", editingNoteId: null, editingCustomRoutineId: null, currentCustomRoutines: [] }
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
    state.editingNoteId = null
    routineForm.resetNote()
  }

  function resetCustomRoutineForm() {
    state.editingCustomRoutineId = null
    routineForm.resetCustomRoutine()
  }

  function renderAdultSelector() {
    window.ProfessionalRoutinesView.renderAdultSelector(state.assignedAdults, state.activeOlderAdultId)
  }

  const renderMedicineRoutine = window.ProfessionalRoutinesView.renderMedicine

  function renderCustomRoutines(routines) {
    state.currentCustomRoutines = routines
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
    state.assignedAdults = await routineService.loadAdults()
  }

  async function loadRoutinesAndNotes() {
    if (!state.activeOlderAdultId) {
      renderEmptyState("No tienes adultos mayores asignados por ahora.")
      return
    }

    try {
      const selectedAdult = state.assignedAdults.find((adult) => String(adult.id) === String(state.activeOlderAdultId))
      const [routineData, notesData, customRoutineData] = await routineService.loadDashboard(state.activeOlderAdultId)

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
      updateAdultUrl(state.activeOlderAdultId)
    } catch (error) {
      renderEmptyState(error.message)
    }
  }

  const parseActivities = routineForm.parseActivities
  const isValidSchedule = routineForm.isValidSchedule

  const { saveCustomRoutine, startEditCustomRoutine, deleteCustomRoutine, completeCustomRoutineActivity, saveNote, startEditNote, deleteNote } = window.ProfessionalRoutinesActions.create({ state, routineService, parseActivities, isValidSchedule, setCustomRoutineMessage, setMessage, firstValidationMessage, resetCustomRoutineForm, resetNoteForm, loadRoutinesAndNotes, showProfessionalAlert, showProfessionalConfirm })

  async function initialize() {
    try {
      await loadAdults()

      if (!state.assignedAdults.length) {
        renderAdultSelector()
        renderEmptyState("No tienes adultos mayores asignados por ahora.")
        return
      }

      const requestedAdultId = getRequestedAdultId()
      const hasRequestedAdult = state.assignedAdults.some((adult) => String(adult.id) === String(requestedAdultId))
      state.activeOlderAdultId = hasRequestedAdult ? requestedAdultId : String(state.assignedAdults[0].id)

      renderAdultSelector()
      await loadRoutinesAndNotes()
    } catch (error) {
      renderEmptyState(error.message)
    }
  }

  window.ProfessionalRoutinesEvents.bind({
    async changeAdult(adultId) {
      state.activeOlderAdultId = adultId
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
