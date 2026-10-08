import { beforeEach, describe, expect, it, vi } from "vitest"

function renderRoutinesPage() {
  document.body.innerHTML = `
    <select id="professionalRoutineAdultSelector"></select>
    <span id="professionalCustomRoutineTotal"></span>
    <span id="professionalRoutineTotal"></span>
    <span id="professionalRoutinePending"></span>
    <span id="professionalRoutineAdministered"></span>
    <span id="professionalWeeklyNotesCount"></span>
    <span id="professionalRoutineWeekRange"></span>
    <span id="professionalRoutineAdultMeta"></span>
    <div id="professionalCustomRoutinesList"></div>
    <div id="professionalRoutinesList"></div>
    <div id="professionalRoutineNotesList"></div>
  `
}

describe("panel de rutinas del cuidador profesional", () => {
  let actions

  beforeEach(async () => {
    renderRoutinesPage()
    await import("../../js/ui-utils.js")

    window.ProfessionalCare = {
      escapeHtml: window.CuidadoUi.escapeHtml,
      formatShortDate: window.CuidadoUi.formatShortDate,
      renderEmpty: (message) => `<div class="empty-state">${window.CuidadoUi.escapeHtml(message)}</div>`,
    }
    window.ProfessionalRoutineForm = {
      firstValidationMessage: vi.fn(),
      isValidSchedule: vi.fn(() => true),
      parseActivities: vi.fn(),
      resetCustomRoutine: vi.fn(),
      resetNote: vi.fn(),
    }
    window.ProfessionalRoutinesActions = {
      create: vi.fn(() => ({
        saveCustomRoutine: vi.fn(),
        startEditCustomRoutine: vi.fn(),
        deleteCustomRoutine: vi.fn(),
        completeCustomRoutineActivity: vi.fn(),
        saveNote: vi.fn(),
        startEditNote: vi.fn(),
        deleteNote: vi.fn(),
      })),
    }
    window.ProfessionalRoutinesEvents = {
      bind: vi.fn((boundActions) => {
        actions = boundActions
      }),
    }

    await import("../../js/cuidador-profesional/routines-view.js")
  })

  it("carga y renderiza las rutinas con sus actividades", async () => {
    window.ProfessionalRoutinesService = {
      loadAdults: vi.fn().mockResolvedValue([{ id: 7, full_name: "María López", room: "12" }]),
      loadDashboard: vi.fn().mockResolvedValue([
        {
          summary: { total: 1, pending_today: 1, administered_today: 0 },
          routine: [{ medication_name: "Losartán", older_adult_name: "María López", due_today: true }],
        },
        { week: { start: "2026-09-21", end: "2026-09-27" }, notes: [] },
        {
          rutinas: [{
            id: 10,
            nombre: "Rutina matutina",
            horario: "08:00",
            actividades: ["Desayunar", "Caminar"],
          }],
        },
      ]),
    }

    await import("../../js/cuidador-profesional/routines.js")
    await actions.initialize()

    expect(window.ProfessionalRoutinesService.loadDashboard).toHaveBeenCalledWith("7")
    expect(document.getElementById("professionalRoutineTotal").textContent).toBe("1")
    expect(document.getElementById("professionalRoutinesList").textContent).toContain("Losartán")
    expect(document.getElementById("professionalCustomRoutinesList").textContent).toContain("Rutina matutina")
    expect(document.getElementById("professionalCustomRoutinesList").textContent).toContain("Desayunar")
    expect(document.getElementById("professionalCustomRoutinesList").textContent).toContain("Caminar")
  })

  it("muestra el error de API en todas las listas", async () => {
    window.ProfessionalRoutinesService = {
      loadAdults: vi.fn().mockResolvedValue([{ id: 7, full_name: "María López" }]),
      loadDashboard: vi.fn().mockRejectedValue(new Error("No se pudieron cargar las rutinas.")),
    }

    await import("../../js/cuidador-profesional/routines.js")
    await actions.initialize()

    ;["professionalRoutinesList", "professionalRoutineNotesList", "professionalCustomRoutinesList"].forEach((id) => {
      expect(document.getElementById(id).textContent).toContain("No se pudieron cargar las rutinas.")
    })
    expect(document.getElementById("professionalRoutineTotal").textContent).toBe("0")
  })
})
