(() => {
  function bind(actions) {
    document.addEventListener("DOMContentLoaded", () => {
      document.getElementById("professionalRoutineAdultSelector")?.addEventListener("change", async (event) => {
        await actions.changeAdult(event.target.value)
      })

      document.getElementById("professionalCustomRoutineForm")?.addEventListener("submit", async (event) => {
        event.preventDefault()
        await actions.saveCustomRoutine()
      })

      document.getElementById("cancelCustomRoutineEdit")?.addEventListener("click", actions.cancelCustomRoutine)

      document.getElementById("professionalRoutineNoteForm")?.addEventListener("submit", async (event) => {
        event.preventDefault()
        await actions.saveNote()
      })

      document.getElementById("cancelRoutineNoteEdit")?.addEventListener("click", actions.cancelNote)

      document.getElementById("professionalRoutineNotesList")?.addEventListener("click", async (event) => {
        const button = event.target.closest("button[data-action][data-id]")
        if (!button) return
        if (button.dataset.action === "edit") actions.editNote(button.dataset.id)
        if (button.dataset.action === "delete") await actions.deleteNote(button.dataset.id)
      })

      document.getElementById("professionalCustomRoutinesList")?.addEventListener("click", async (event) => {
        const button = event.target.closest("button[data-custom-routine-action][data-id]")
        if (!button) return
        const action = button.dataset.customRoutineAction
        if (action === "edit") actions.editCustomRoutine(button.dataset.id)
        if (action === "delete") await actions.deleteCustomRoutine(button.dataset.id)
        if (action === "complete") {
          await actions.completeActivity(button.dataset.id, button.dataset.activityIndex)
        }
      })

      actions.initialize()
    })
  }

  window.ProfessionalRoutinesEvents = Object.freeze({ bind })
})()
