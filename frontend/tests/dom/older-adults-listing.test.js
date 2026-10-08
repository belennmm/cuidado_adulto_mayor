import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
}

describe("listado de adultos mayores", () => {
  it("solicita los registros y simula la carga de la lista", async () => {
    document.body.innerHTML = `
      <input id="olderAdultSearchInput">
      <div id="olderAdultsTableBody"></div>
    `
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn().mockResolvedValue({
        older_adults: [{ id: 1, full_name: "Rosa Martínez", age: 82, caregiver_family: "Ana", room: "12", status: "Estable" }],
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/admin/adultos-mayores.js")
    await flushRequests()

    expect(window.CuidadoApi.getToken).toHaveBeenCalledWith(["admin"])
    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/older-adults", expect.objectContaining({
      expectedRoles: ["admin"],
    }))
    expect(document.querySelectorAll(".older-adult-row")).toHaveLength(1)
  })
})
