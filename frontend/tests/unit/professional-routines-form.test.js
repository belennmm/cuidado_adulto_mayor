import { beforeEach, describe, expect, it } from "vitest"

describe("ProfessionalRoutineForm", () => {
  beforeEach(async () => {
    await import("../../js/cuidador-profesional/routines-form.js")
  })

  it("separa y limpia actividades", () => {
    expect(window.ProfessionalRoutineForm.parseActivities("Caminar, Comer\n Descansar "))
      .toEqual(["Caminar", "Comer", "Descansar"])
  })

  it("valida horarios reales", () => {
    expect(window.ProfessionalRoutineForm.isValidSchedule("08:30")).toBe(true)
    expect(window.ProfessionalRoutineForm.isValidSchedule("24:00")).toBe(false)
    expect(window.ProfessionalRoutineForm.isValidSchedule("8:30")).toBe(false)
  })

  it("obtiene el primer mensaje de validación", () => {
    expect(window.ProfessionalRoutineForm.firstValidationMessage({
      errors: { nombre: ["El nombre es obligatorio."] },
    })).toBe("El nombre es obligatorio.")
  })

  it("restablece el formulario de notas", () => {
    document.body.innerHTML = `
      <textarea id="routineNoteInput">Texto</textarea>
      <span id="routineNoteFormTitle"></span>
      <button id="saveRoutineNoteButton"></button>
      <button id="cancelRoutineNoteEdit"></button>`

    window.ProfessionalRoutineForm.resetNote()
    expect(document.getElementById("routineNoteInput").value).toBe("")
    expect(document.getElementById("routineNoteFormTitle").textContent).toBe("Agregar nota")
    expect(document.getElementById("cancelRoutineNoteEdit").hidden).toBe(true)
  })
})
