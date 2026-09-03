import { describe, expect, it, vi } from "vitest"

async function flushAsyncEvents() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe("inventario individual de medicamentos", () => {
  it("carga los adultos mayores y filtra los medicamentos al seleccionar uno", async () => {
    document.body.innerHTML = `
      <div id="statsFilterGroup"></div>
      <div id="medicinesRankingList"></div>
      <select id="inventoryOlderAdultFilter"></select>
      <select id="medicationOlderAdult"></select>
      <p id="inventoryAdultSelectionStatus"></p>
      <div id="inventoryList"></div>
      <section id="medicinesStatsLayout"></section>
    `

    window.AuthSession = {
      getUser: vi.fn(() => ({ id: 1, role: "admin" })),
    }
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path) => {
        if (path === "/admin/older-adults") {
          return Promise.resolve({
            older_adults: [
              { id: 10, full_name: "Rosa Martínez" },
              { id: 20, full_name: "Carlos López" },
            ],
          })
        }

        return Promise.resolve({
          items: [],
          inventory: [
            { id: 1, older_adult_id: 10, older_adult_name: "Rosa Martínez", name: "Losartán", quantity: 12, unit: "tabletas", minimum_stock: 5, status: "available", status_label: "Disponible" },
            { id: 2, older_adult_id: 20, older_adult_name: "Carlos López", name: "Metformina", quantity: 20, unit: "tabletas", minimum_stock: 8, status: "available", status_label: "Disponible" },
          ],
        })
      }),
    }

    await import("../../js/admin/dashboard-medicines-stats.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))
    await flushAsyncEvents()

    const selector = document.getElementById("inventoryOlderAdultFilter")
    expect([...selector.options].map((option) => option.textContent)).toEqual([
      "Todos los adultos mayores",
      "Rosa Martínez",
      "Carlos López",
    ])

    selector.value = "10"
    selector.dispatchEvent(new Event("change", { bubbles: true }))

    expect(document.getElementById("inventoryList").textContent).toContain("Losartán")
    expect(document.getElementById("inventoryList").textContent).not.toContain("Metformina")
    expect(document.getElementById("inventoryAdultSelectionStatus").textContent).toContain("Rosa Martínez")
  })
})
