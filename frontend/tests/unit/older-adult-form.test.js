import { beforeEach, describe, expect, it, vi } from "vitest"

describe("OlderAdultForm", () => {
  beforeEach(async () => {
    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
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

  it("construye el mismo payload para creación con medicamentos", () => {
    document.body.innerHTML = `
      <form id="adultForm">
        <input name="fullName" value="Rosa Martínez">
        <input name="age" value="82">
        <input name="birthdate" value="1944-02-10">
        <select name="gender"><option value="femenino" selected>Femenino</option></select>
        <input name="room" value="12">
        <select name="status"><option value="estable" selected>Estable</option></select>
        <select name="caregiverFamily"><option value="3" selected>Familia</option></select>
        <select name="professionalCaregiver"><option value="5" selected>Profesional</option></select>
        <input name="contactName" value="Ana">
        <input name="contactPhone" value="5555-5555">
        <input name="allergies" value="Ninguna">
        <textarea name="medicalHistory">Control</textarea>
        <textarea name="notes">Observación</textarea>
      </form>
      <div id="medicines"></div>
    `
    const controller = window.OlderAdultForm.create({
      form: document.getElementById("adultForm"),
      medicinesList: document.getElementById("medicines"),
    })
    controller.medicines.add({ name: "Losartán", dosage: "1 tableta" })

    expect(controller.buildPayload()).toMatchObject({
      full_name: "Rosa Martínez",
      age: "82",
      family_caregiver_id: "3",
      professional_caregiver_id: "5",
      medications: [{ name: "Losartán", dosage: "1 tableta" }],
    })
  })

  it("llena los campos de edición y conserva ids de medicamentos", () => {
    document.body.innerHTML = `
      <form id="adultForm">
        <input name="fullName"><input name="age"><input name="birthdate">
        <select name="gender"><option value="femenino">Femenino</option></select>
        <input name="room"><select name="status"><option value="estable">Estable</option></select>
        <select name="caregiverFamily"><option value="3">Familia</option></select>
        <select name="professionalCaregiver"><option value="5">Profesional</option></select>
        <input name="contactName"><input name="contactPhone"><input name="allergies">
        <textarea name="medicalHistory"></textarea><textarea name="notes"></textarea>
      </form>
      <div id="medicines"></div>
    `
    const form = document.getElementById("adultForm")
    const controller = window.OlderAdultForm.create({ form, medicinesList: document.getElementById("medicines"), includeMedicationIds: true })
    controller.fill({
      full_name: "Rosa",
      birthdate: "1944-02-10T00:00:00",
      family_caregiver_id: 3,
      professional_caregiver_id: 5,
      medications: [{ id: 9, name: "Losartán", days: ["lunes"] }],
    })

    expect(form.elements.fullName.value).toBe("Rosa")
    expect(form.elements.birthdate.value).toBe("1944-02-10")
    expect(controller.buildPayload().medications[0]).toMatchObject({ id: 9, name: "Losartán" })
  })

  it("carga ambos tipos de cuidadores desde un solo controlador", async () => {
    document.body.innerHTML = `
      <form id="adultForm">
        <select name="caregiverFamily"></select>
        <select name="professionalCaregiver"></select>
      </form>
      <div id="medicines"></div>
    `
    window.CuidadoApi = {
      getToken: vi.fn(() => "token"),
      fetchJson: vi.fn()
        .mockResolvedValueOnce({ users: [{ id: 3, name: "Marta" }] })
        .mockResolvedValueOnce({ users: [{ id: 5, name: "Pedro" }] }),
    }
    const form = document.getElementById("adultForm")
    const controller = window.OlderAdultForm.create({ form, medicinesList: document.getElementById("medicines") })
    await controller.loadCaregivers({ familySelectedId: 3, professionalSelectedId: 5 })

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledTimes(2)
    expect(form.elements.caregiverFamily.value).toBe("3")
    expect(form.elements.professionalCaregiver.value).toBe("5")
  })
})
