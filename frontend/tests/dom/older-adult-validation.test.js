import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
}

describe("validación del formulario de adulto mayor", () => {
  it("no envía el formulario cuando falta el nombre completo", async () => {
    document.body.innerHTML = `
      <form id="newOlderAdultForm"><input name="fullName" value=""><button class="primary-button" type="submit">Crear adulto mayor</button></form>
      <div id="medicinesList"></div><button id="addMedicineButton"></button>
    `
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn().mockResolvedValue({ users: [] }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/older-adult-form.js")
    await import("../../js/admin/new-adulto-mayor.js")
    await flushRequests()
    document.getElementById("newOlderAdultForm").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.showAdminAlert).toHaveBeenCalledWith("Ingresa el nombre completo del adulto mayor.", { variant: "error" })
    expect(window.CuidadoApi.fetchJson).not.toHaveBeenCalledWith("/admin/older-adults", expect.anything())
  })
})
