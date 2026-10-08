import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function renderForm() {
  document.body.innerHTML = `
    <form id="newOlderAdultForm">
      <input name="fullName" value="Rosa Martínez"><input name="age" value="82">
      <input name="birthdate" value="1944-02-10"><select name="gender"><option value="Femenino" selected>Femenino</option></select>
      <input name="room" value="12"><select name="status"><option value="Estable" selected>Estable</option></select>
      <select name="caregiverFamily"></select><select name="professionalCaregiver"></select>
      <input name="contactName" value="Ana"><input name="contactPhone" value="55555555">
      <input name="allergies" value="Ninguna"><textarea name="medicalHistory">Control</textarea><textarea name="notes">Sin novedades</textarea>
      <button class="primary-button" type="submit">Crear adulto mayor</button>
    </form>
    <div id="medicinesList"></div><button id="addMedicineButton"></button>
  `
}

describe("creación de adulto mayor", () => {
  it("envía el formulario válido y navega al listado", async () => {
    renderForm()
    window.navigateWithLoading = vi.fn()
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path) => {
        if (path.includes("caregivers")) return Promise.resolve({ users: [] })
        return Promise.resolve({ message: "Adulto mayor creado correctamente." })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/older-adult-form.js")
    await import("../../js/admin/new-adulto-mayor.js")
    await flushRequests()
    document.getElementById("newOlderAdultForm").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/older-adults", expect.objectContaining({
      method: "POST",
      body: expect.stringContaining('"full_name":"Rosa Martínez"'),
    }))
    expect(window.showAdminAlert).toHaveBeenCalledWith("Adulto mayor creado correctamente.", { variant: "success" })
    expect(window.navigateWithLoading).toHaveBeenCalledWith("./adultos-mayores.html")
  })
})
