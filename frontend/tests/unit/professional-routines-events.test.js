import { fireEvent } from "@testing-library/dom"
import { beforeEach, describe, expect, it, vi } from "vitest"

describe("ProfessionalRoutinesEvents", () => {
  beforeEach(async () => {
    document.body.innerHTML = `
      <select id="professionalRoutineAdultSelector"><option value="3">Luis</option></select>
      <form id="professionalCustomRoutineForm"></form>
      <div id="professionalRoutineNotesList"><button data-action="edit" data-id="9">Editar</button></div>`
    await import("../../js/cuidador-profesional/routines-events.js")
  })

  it("conecta selector, formulario y acciones delegadas", async () => {
    const actions = {
      changeAdult: vi.fn(), saveCustomRoutine: vi.fn(), cancelCustomRoutine: vi.fn(),
      saveNote: vi.fn(), cancelNote: vi.fn(), editNote: vi.fn(), deleteNote: vi.fn(),
      editCustomRoutine: vi.fn(), deleteCustomRoutine: vi.fn(), completeActivity: vi.fn(),
      initialize: vi.fn(),
    }
    window.ProfessionalRoutinesEvents.bind(actions)
    document.dispatchEvent(new Event("DOMContentLoaded"))

    fireEvent.change(document.getElementById("professionalRoutineAdultSelector"))
    fireEvent.submit(document.getElementById("professionalCustomRoutineForm"))
    fireEvent.click(document.querySelector("button[data-action='edit']"))

    expect(actions.initialize).toHaveBeenCalledOnce()
    expect(actions.changeAdult).toHaveBeenCalledWith("3")
    expect(actions.saveCustomRoutine).toHaveBeenCalledOnce()
    expect(actions.editNote).toHaveBeenCalledWith("9")
  })
})
