import { beforeEach, describe, expect, it, vi } from "vitest"

describe("OlderAdultForm", () => {
  beforeEach(async () => {
    await import("../../js/ui-utils.js")
    await import("../../js/admin/older-adult-form.js")
  })

  it("agrega y lee medicamentos", () => {
    document.body.innerHTML = '<div id="medicines"></div>'
    const manager = window.OlderAdultForm.createMedicineManager(document.getElementById("medicines"))
    manager.add({ id: 7, name: "Aspirina", dosage: "1 tableta", days: ["lunes"] })

    expect(manager.read({ includeIds: true })).toEqual([{
      id: 7,
      name: "Aspirina",
      dosage: "1 tableta",
      schedule: null,
      days: ["lunes"],
      notes: null,
    }])
  })

  it("restablece la lista con una tarjeta vacía", () => {
    document.body.innerHTML = '<div id="medicines"></div>'
    const list = document.getElementById("medicines")
    const manager = window.OlderAdultForm.createMedicineManager(list)

    manager.fill([])
    expect(list.querySelectorAll(".medicine-card")).toHaveLength(1)
    expect(manager.read()).toEqual([])
  })

  it("carga opciones de cuidadores y conserva la selección", async () => {
    document.body.innerHTML = '<select id="caregiver"></select>'
    window.CuidadoApi = {
      getToken: vi.fn(() => "token"),
      fetchJson: vi.fn().mockResolvedValue({ users: [{ id: 2, name: "Marta" }] }),
    }

    const select = document.getElementById("caregiver")
    await window.OlderAdultForm.loadCaregiverOptions(select, {
      path: "/admin/family-caregivers",
      placeholder: "Seleccione cuidador",
      selectedId: 2,
    })

    expect(select.options).toHaveLength(2)
    expect(select.value).toBe("2")
  })
})
