import { beforeEach, describe, expect, it } from "vitest"

describe("ProfessionalRoutinesView", () => {
  beforeEach(async () => {
    await import("../../js/ui-utils.js")
    window.ProfessionalCare = {
      escapeHtml: window.CuidadoUi.escapeHtml,
      formatShortDate: window.CuidadoUi.formatShortDate,
      renderEmpty: (message) => `<div class="empty">${message}</div>`,
    }
    await import("../../js/cuidador-profesional/routines-view.js")
  })

  it("renderiza medicamentos con su estado", () => {
    const html = window.ProfessionalRoutinesView.renderMedicine({
      medication_name: "Aspirina",
      older_adult_name: "Luis",
      due_today: true,
    })
    expect(html).toContain("Aspirina")
    expect(html).toContain("Pendiente")
  })

  it("renderiza notas y actualiza el contador", () => {
    document.body.innerHTML = '<span id="professionalWeeklyNotesCount"></span><div id="professionalRoutineNotesList"></div>'
    window.ProfessionalRoutinesView.renderNotes([{ id: 1, note_date: "2026-09-20", content: "Sin novedad" }])
    expect(document.getElementById("professionalWeeklyNotesCount").textContent).toBe("1")
    expect(document.getElementById("professionalRoutineNotesList").textContent).toContain("Sin novedad")
  })

  it("restablece contadores al mostrar un estado vacío", () => {
    document.body.innerHTML = '<span id="professionalRoutineTotal">5</span><div id="professionalRoutinesList"></div>'
    window.ProfessionalRoutinesView.renderEmpty("Selecciona un adulto mayor")
    expect(document.getElementById("professionalRoutineTotal").textContent).toBe("0")
    expect(document.getElementById("professionalRoutinesList").textContent).toContain("Selecciona un adulto mayor")
  })
})
