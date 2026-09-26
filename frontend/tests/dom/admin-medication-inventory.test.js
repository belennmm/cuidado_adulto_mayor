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
            { id: 1, older_adult_id: 10, older_adult_name: "Rosa Martínez", medication_id: 1, name: "Losartán", quantity: 12, unit: "tabletas", minimum_stock: 5, expiration_date: "2030-01-01", status: "available", status_label: "Disponible" },
            { id: 2, older_adult_id: 20, older_adult_name: "Carlos López", medication_id: 2, name: "Metformina", quantity: 20, unit: "tabletas", minimum_stock: 8, expiration_date: "2030-01-01", status: "available", status_label: "Disponible" },
            { id: 3, older_adult_id: 10, older_adult_name: "Rosa Martínez", medication_id: 3, name: "Rivotril", quantity: 20, unit: "tabletas", minimum_stock: 5, expiration_date: "2030-01-01", status: "available", status_label: "Disponible" },
            { id: 4, older_adult_id: 20, older_adult_name: "Carlos López", medication_id: 3, name: "Rivotril", quantity: 10, unit: "tabletas", minimum_stock: 5, expiration_date: "2031-01-01", status: "available", status_label: "Disponible" },
            { id: 5, older_adult_id: null, older_adult_name: null, medication_id: 3, name: "Rivotril", quantity: 30, unit: "tabletas", minimum_stock: 3, expiration_date: null, status: "available", status_label: "Disponible" },
          ],
        })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/admin/medication-stats-service.js")
    await import("../../js/admin/medication-stats-view.js")
    await import("../../js/admin/medication-stats-dialogs.js")
    await import("../../js/admin/dashboard-medicines-stats.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))
    await flushAsyncEvents()

    const selector = document.getElementById("inventoryOlderAdultFilter")
    expect([...selector.options].map((option) => option.textContent)).toEqual([
      "Inventario general consolidado",
      "Stock sin asignar",
      "Rosa Martínez",
      "Carlos López",
    ])

    expect(document.getElementById("inventoryList").textContent).toContain("60 tabletas")
    expect(document.getElementById("inventoryList").textContent).toContain("Vencimientos variados")
    expect(document.getElementById("inventoryList").textContent).toContain("13 tabletas")

    const consolidatedCard = [...document.querySelectorAll(".inventory-item")]
      .find((card) => card.textContent.includes("Rivotril"))
    expect(consolidatedCard.querySelector("[data-action='increase']").textContent).toBe("Sumar stock")
    expect(consolidatedCard.querySelector("[data-action='decrease']")).toBeNull()
    expect(consolidatedCard.querySelector("[data-action='edit']")).toBeNull()
    expect(consolidatedCard.querySelector("[data-action='delete']")).toBeNull()

    selector.value = "unassigned"
    selector.dispatchEvent(new Event("change", { bubbles: true }))
    const unassignedCard = [...document.querySelectorAll(".inventory-item")]
      .find((card) => card.textContent.includes("Rivotril"))
    expect(unassignedCard.textContent).toContain("30 tabletas")
    expect(unassignedCard.textContent).toContain("Stock sin asignar")
    expect(unassignedCard.querySelector("[data-action='edit']")).not.toBeNull()
    expect(unassignedCard.querySelector("[data-action='decrease']")).not.toBeNull()

    selector.value = "10"
    selector.dispatchEvent(new Event("change", { bubbles: true }))

    expect(document.getElementById("inventoryList").textContent).toContain("Losartán")
    expect(document.getElementById("inventoryList").textContent).not.toContain("Metformina")
    expect(document.getElementById("inventoryAdultSelectionStatus").textContent).toContain("Rosa Martínez")
  })
})
