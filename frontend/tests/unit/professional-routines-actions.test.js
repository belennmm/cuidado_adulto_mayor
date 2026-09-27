import { beforeEach, describe, expect, it, vi } from "vitest"

describe("acciones de rutinas del cuidador profesional", () => {
  let state
  let routineService
  let setCustomRoutineMessage
  let loadRoutinesAndNotes
  let actions

  beforeEach(async () => {
    document.body.innerHTML = `
      <form id="professionalCustomRoutineForm">
        <input id="customRoutineName" value="Rutina matutina" />
        <input id="customRoutineSchedule" value="08:00" />
        <textarea id="customRoutineActivities">Desayunar\nCaminar</textarea>
      </form>
      <h2 id="customRoutineFormTitle">Crear rutina</h2>
      <button id="saveCustomRoutineButton">Guardar rutina</button>
      <button id="cancelCustomRoutineEdit" hidden>Cancelar edición</button>
    `
    document.getElementById("professionalCustomRoutineForm").scrollIntoView = vi.fn()

    state = {
      activeOlderAdultId: "7",
      editingCustomRoutineId: null,
      editingNoteId: null,
      currentCustomRoutines: [],
    }
    routineService = {
      saveRoutine: vi.fn().mockResolvedValue({}),
      completeActivity: vi.fn().mockResolvedValue({}),
    }
    setCustomRoutineMessage = vi.fn()
    loadRoutinesAndNotes = vi.fn().mockResolvedValue()

    await import("../../js/cuidador-profesional/routines-actions.js")
    actions = window.ProfessionalRoutinesActions.create({
      state,
      routineService,
      parseActivities: (value) => value.split(/\n/).map((item) => item.trim()).filter(Boolean),
      isValidSchedule: (value) => /^([01]\d|2[0-3]):[0-5]\d$/.test(value),
      setCustomRoutineMessage,
      setMessage: vi.fn(),
      firstValidationMessage: (error) => error.message,
      resetCustomRoutineForm: vi.fn(() => {
        state.editingCustomRoutineId = null
      }),
      resetNoteForm: vi.fn(),
      loadRoutinesAndNotes,
      showProfessionalAlert: vi.fn().mockResolvedValue(),
      showProfessionalConfirm: vi.fn().mockResolvedValue(true),
    })
  })

  it("crea una rutina con sus actividades y recarga el panel", async () => {
    await actions.saveCustomRoutine()

    expect(routineService.saveRoutine).toHaveBeenCalledWith({
      id: null,
      olderAdultId: "7",
      nombre: "Rutina matutina",
      horario: "08:00",
      actividades: ["Desayunar", "Caminar"],
    })
    expect(setCustomRoutineMessage).toHaveBeenCalledWith("Rutina creada correctamente.")
    expect(loadRoutinesAndNotes).toHaveBeenCalledOnce()
  })

  it("carga una rutina existente y envía sus cambios", async () => {
    state.currentCustomRoutines = [{
      id: 10,
      nombre: "Rutina nocturna",
      horario: "20:30",
      actividades: ["Cenar", "Tomar medicamento"],
    }]

    actions.startEditCustomRoutine(10)

    expect(document.getElementById("customRoutineName").value).toBe("Rutina nocturna")
    expect(document.getElementById("customRoutineSchedule").value).toBe("20:30")
    expect(document.getElementById("customRoutineActivities").value).toBe("Cenar\nTomar medicamento")
    expect(document.getElementById("customRoutineFormTitle").textContent).toBe("Editar rutina")

    document.getElementById("customRoutineName").value = "Rutina nocturna actualizada"
    await actions.saveCustomRoutine()

    expect(routineService.saveRoutine).toHaveBeenCalledWith(expect.objectContaining({
      id: 10,
      nombre: "Rutina nocturna actualizada",
      actividades: ["Cenar", "Tomar medicamento"],
    }))
    expect(setCustomRoutineMessage).toHaveBeenCalledWith("Rutina actualizada correctamente.")
  })

  it("marca una actividad como completada y actualiza la vista", async () => {
    await actions.completeCustomRoutineActivity(10, 1)

    expect(routineService.completeActivity).toHaveBeenCalledWith(10, 1)
    expect(setCustomRoutineMessage).toHaveBeenCalledWith("Actividad marcada como completada.")
    expect(loadRoutinesAndNotes).toHaveBeenCalledOnce()
  })
})
