import { describe, expect, it, vi } from "vitest"

describe("listado de usuarios", () => {
  it("consulta y muestra los usuarios simulados", async () => {
    document.body.innerHTML = '<input id="userSearchInput"><div id="usersTableBody"></div>'
    window.CuidadoApi = {
      fetchJson: vi.fn().mockResolvedValue({
        users: [
          { id: 1, name: "Ana Pérez", role: "cuidador_familiar", email: "ana@example.test", phone: "5551234", is_approved: true },
          { id: 2, name: "Luis Díaz", role: "cuidador_profesional", email: "luis@example.test", is_approved: false },
        ],
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/users.js")
    await Promise.resolve()
    await Promise.resolve()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/users", expect.objectContaining({ expectedRoles: ["admin"] }))
    expect(document.querySelectorAll(".user-row")).toHaveLength(2)
    expect(document.getElementById("usersTableBody").textContent).toContain("Ana Pérez")
    expect(document.getElementById("usersTableBody").textContent).toContain("Luis Díaz")
    expect(document.querySelector(".approve-button").dataset.id).toBe("2")
  })
})
