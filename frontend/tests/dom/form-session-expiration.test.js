import { beforeEach, describe, expect, it, vi } from "vitest"

const expiredMessage = "Tu sesión ha expirado. Inicia sesión nuevamente para continuar."
const flush = () => new Promise((resolve) => setTimeout(resolve, 0))
const response = (data, status = 200) => ({
  ok: status < 400, status, headers: { get: () => "application/json" }, json: async () => data,
})

function mockRequests(status, savedData) {
  const request = vi.fn(async (_path, options = {}) => {
    if (["POST", "PUT", "PATCH", "DELETE"].includes(options.method)) {
      return response(status === 401 ? { message: "Unauthenticated." }
        : status === 422 ? { message: "Revisa los datos enviados." } : savedData, status)
    }
    return response({ users: [], user: { id: 3, role: "familiar", name: "Nombre anterior", email: "family@example.test" } })
  })
  vi.stubGlobal("fetch", request)
  return request
}

function expectRejectedSubmission(status) {
  expect(window.showAdminAlert).toHaveBeenCalledWith(status === 401 ? expiredMessage : "Revisa los datos enviados.", expect.objectContaining({ variant: "error" }))
  expect(window.showAdminAlert.mock.calls.some(([, options]) => options?.variant === "success")).toBe(false)
  expect(window.navigateWithLoading).not.toHaveBeenCalled()
  expect(Boolean(document.getElementById("sessionExpiredNotice"))).toBe(status === 401)
  if (status === 401) {
    expect(window.AuthSession.getUser()).toBeNull()
    expect(document.querySelector("#sessionExpiredNotice a").getAttribute("href")).toBe("../../index.html")
  }
}

beforeEach(async () => {
  await import("../../js/api-config.js")
  await import("../../js/auth-session.js")
  await import("../../js/ui-utils.js")
  window.navigateWithLoading = vi.fn()
  window.showAdminAlert = vi.fn().mockResolvedValue()
})

describe("expediente administrativo con medicamentos: una sola escritura", () => {
  it.each([401, 422, 201])("maneja la respuesta %s sin confundir rechazo con guardado", async (status) => {
    document.body.innerHTML = `
      <form id="newOlderAdultForm"><input name="fullName" value="Persona en edición">
        <textarea name="medicalHistory">Historia sin enviar</textarea>
        <button type="submit" class="primary-button">Crear adulto mayor</button></form>
      <div id="medicinesList"></div><button id="addMedicineButton"></button>`
    window.AuthSession.saveUser({ id: 1, role: "admin" })
    const request = mockRequests(status, { message: "Expediente creado." })
    await import("../../js/form-utils.js")
    await import("../../js/admin/older-adult-form.js")
    await import("../../js/admin/new-adulto-mayor.js")
    await flush()
    document.getElementById("medicineName1").value = "Medicamento de prueba"
    const form = document.getElementById("newOlderAdultForm")
    const reset = vi.spyOn(form, "reset")
    request.mockClear()
    form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }))
    await flush()

    expect(request).toHaveBeenCalledOnce()
    const [path, options] = request.mock.calls[0]
    expect(path).toBe("/api/admin/older-adults")
    expect(JSON.parse(options.body)).toMatchObject({ full_name: "Persona en edición", medications: [{ name: "Medicamento de prueba" }] })
    if (status === 201) {
      expect(window.showAdminAlert).toHaveBeenCalledWith("Expediente creado.", { variant: "success" })
      expect(window.navigateWithLoading).toHaveBeenCalledWith("./adultos-mayores.html")
    } else {
      expectRejectedSubmission(status)
      expect(reset).not.toHaveBeenCalled()
      expect(form.elements.fullName.value).toBe("Persona en edición")
      expect(form.elements.medicalHistory.value).toBe("Historia sin enviar")
      expect(document.getElementById("medicineName1").value).toBe("Medicamento de prueba")
    }
    expect(form.querySelector("button").disabled).toBe(false)
  })
})

describe("rutina profesional con actividades: una sola escritura", () => {
  it.each([401, 422, 201])("actualiza el panel únicamente tras confirmar el guardado (%s)", async (status) => {
    document.body.innerHTML = `<form id="professionalCustomRoutineForm">
      <input id="customRoutineName" value="Rutina en edición"><input id="customRoutineSchedule" value="08:00">
      <textarea id="customRoutineActivities">Desayunar\nCaminar</textarea>
      <button id="saveCustomRoutineButton">Guardar rutina</button></form>`
    window.AuthSession.saveUser({ id: 2, role: "profesional" })
    const request = mockRequests(status, { id: 9 })
    await import("../../js/cuidador-profesional/professional-api.js")
    await import("../../js/cuidador-profesional/routines-service.js")
    await import("../../js/cuidador-profesional/routines-actions.js")
    const reset = vi.fn(() => document.getElementById("professionalCustomRoutineForm").reset())
    const reload = vi.fn().mockResolvedValue()
    const message = vi.fn()
    const actions = window.ProfessionalRoutinesActions.create({
      state: { activeOlderAdultId: "7", editingCustomRoutineId: null },
      routineService: window.ProfessionalRoutinesService,
      parseActivities: (text) => text.split("\n"), isValidSchedule: () => true,
      firstValidationMessage: (error) => error.message,
      resetCustomRoutineForm: reset, loadRoutinesAndNotes: reload,
      setCustomRoutineMessage: message, showProfessionalAlert: window.showAdminAlert,
    })
    await actions.saveCustomRoutine()

    expect(request).toHaveBeenCalledOnce()
    expect(JSON.parse(request.mock.calls[0][1].body)).toMatchObject({ actividades: ["Desayunar", "Caminar"] })
    if (status === 201) {
      expect(reset).toHaveBeenCalledOnce()
      expect(reload).toHaveBeenCalledOnce()
      expect(window.showAdminAlert).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ variant: "success" }))
    } else {
      expectRejectedSubmission(status)
      expect(reset).not.toHaveBeenCalled()
      expect(reload).not.toHaveBeenCalled()
      expect(message).toHaveBeenCalledWith(status === 401 ? expiredMessage : "Revisa los datos enviados.", true)
      expect(document.getElementById("customRoutineName").value).toBe("Rutina en edición")
      expect(document.getElementById("customRoutineActivities").value).toBe("Desayunar\nCaminar")
    }
  })
})

describe("perfil familiar: no actualiza identidad ni campos tras un rechazo", () => {
  it.each([401, 422, 200])("conserva el flujo correcto ante respuesta %s", async (status) => {
    document.body.innerHTML = `<form id="familySettingsForm">
      ${["fullName", "email", "phone", "location", "birthdate", "role", "currentPassword", "newPassword", "confirmPassword"].map((id) => `<input id="${id}">`).join("")}
      <button type="submit">Guardar</button></form>
      <p id="settingsMessage"></p><p id="profileName"></p><p id="profileRole"></p><p id="profileEmail"></p>`
    window.AuthSession.saveUser({ id: 3, role: "familiar", name: "Nombre anterior" })
    const request = mockRequests(status, { user: { id: 3, role: "familiar", name: "Nombre nuevo" }, message: "Perfil guardado." })
    await import("../../js/cuidador-familiar/family-api.js")
    // Initialize this page once without leaving DOMContentLoaded listeners behind.
    const registration = vi.spyOn(document, "addEventListener")
    await import("../../js/cuidador-familiar/user-settings.js")
    const ready = registration.mock.calls.find(([event]) => event === "DOMContentLoaded")[1]
    document.removeEventListener("DOMContentLoaded", ready)
    registration.mockRestore()
    ready()
    await flush()
    document.getElementById("fullName").value = "Nombre nuevo"
    document.getElementById("phone").value = "55550000"
    const saveUser = vi.spyOn(window.FamilyCare, "saveUser")
    request.mockClear()
    document.getElementById("familySettingsForm").dispatchEvent(new Event("submit", { cancelable: true }))
    await flush()

    expect(request).toHaveBeenCalledOnce()
    expect(request.mock.calls[0][1].method).toBe("PUT")
    if (status === 200) {
      expect(saveUser).toHaveBeenCalledOnce()
      expect(document.getElementById("profileName").textContent).toBe("Nombre nuevo")
      expect(document.getElementById("settingsMessage").classList.contains("success")).toBe(true)
    } else {
      expectRejectedSubmission(status)
      expect(saveUser).not.toHaveBeenCalled()
      expect(document.getElementById("profileName").textContent).toBe("Nombre anterior")
      expect(document.getElementById("fullName").value).toBe("Nombre nuevo")
      expect(document.getElementById("phone").value).toBe("55550000")
      expect(document.getElementById("settingsMessage").classList.contains("success")).toBe(false)
    }
  })
})
