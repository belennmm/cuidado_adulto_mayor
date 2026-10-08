import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe("error de creación de adulto mayor", () => {
  it("muestra el error del backend y restablece el botón", async () => {
    document.body.innerHTML = `
      <form id="newOlderAdultForm"><input name="fullName" value="Rosa Martínez"><button class="primary-button" type="submit">Crear adulto mayor</button></form>
      <div id="medicinesList"></div><button id="addMedicineButton"></button>
    `
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path) => {
        if (path.includes("caregivers")) return Promise.resolve({ users: [] })
        return Promise.reject(new Error("No se pudo crear el adulto mayor."))
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/older-adult-form.js")
    await import("../../js/admin/new-adulto-mayor.js")
    await flushRequests()
    const form = document.getElementById("newOlderAdultForm")
    const button = form.querySelector(".primary-button")
    form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.showAdminAlert).toHaveBeenCalledWith("No se pudo crear el adulto mayor.", { variant: "error" })
    expect(button.disabled).toBe(false)
    expect(button.textContent).toBe("Crear adulto mayor")
  })
})
