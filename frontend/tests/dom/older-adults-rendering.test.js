import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
}

describe("renderizado del listado de adultos mayores", () => {
  it("muestra los datos, estado y acciones de cada registro", async () => {
    document.body.innerHTML = `
      <input id="olderAdultSearchInput">
      <div id="olderAdultsTableBody"></div>
    `
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn().mockResolvedValue({
        older_adults: [{ id: 24, full_name: "José Pérez", age: 79, caregiver_family: "Marta López", room: "B-4", status: "Atención" }],
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/admin/adultos-mayores.js")
    await flushRequests()

    const row = document.querySelector(".older-adult-row")
    expect(row.textContent).toContain("José Pérez")
    expect(row.textContent).toContain("79")
    expect(row.textContent).toContain("Marta López")
    expect(row.textContent).toContain("B-4")
    expect(row.querySelector(".status-badge").classList.contains("status-attention")).toBe(true)
    expect(row.querySelector(".edit-button").dataset.id).toBe("24")
    expect(row.querySelector(".routine-button").dataset.id).toBe("24")
  })
})
