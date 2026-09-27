import { beforeEach, describe, expect, it, vi } from "vitest"

function renderDashboard() {
  document.body.innerHTML = `
    <span id="olderAdultsCount"></span>
    <span id="caregiversCount"></span>
    <span id="incidentsCount"></span>
    <span id="requestsCount"></span>
    <span id="medicineCount"></span>
    <span id="lateEntriesCount"></span>
    <span id="absencesCount"></span>
    <span id="vacationsCount"></span>
    <span id="changesCount"></span>
    <p class="medicine-text"></p>
    <div id="routineList"></div>
  `
}

async function loadDashboard() {
  await import("../../js/admin/dashboard.js")
  await Promise.all([Promise.resolve(), Promise.resolve()])
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe("resumen del panel administrativo", () => {
  beforeEach(async () => {
    renderDashboard()
    await import("../../js/ui-utils.js")
  })

  it("renderiza las estadísticas y los cambios recientes de rutinas", async () => {
    window.CuidadoApi = {
      fetchJson: vi.fn((path) => {
        if (path === "/admin/dashboard-summary") {
          return Promise.resolve({
            stats: { older_adults: 12, caregivers: 5, incidents_today: 2, requests: 3 },
            medications: { pending_today: 4 },
            report: { late_entries: 1, absences: 2, vacation_requests: 3, change_requests: 4 },
          })
        }

        return Promise.resolve({
          rutinas: [{
            id: 8,
            nombre: "Rutina matutina",
            horario: "08:00",
            older_adult_id: 15,
            adulto_mayor: { full_name: "María López" },
            actividades: ["Desayuno", "Medicamento"],
            updated_at: "2026-09-27T08:00:00.000Z",
          }],
        })
      }),
    }

    await loadDashboard()

    expect(document.getElementById("olderAdultsCount").textContent).toBe("12")
    expect(document.getElementById("caregiversCount").textContent).toBe("5")
    expect(document.getElementById("incidentsCount").textContent).toBe("2")
    expect(document.getElementById("requestsCount").textContent).toBe("3")
    expect(document.getElementById("medicineCount").textContent).toBe("4")
    expect(document.querySelector(".medicine-text").textContent).toContain("4 no se han administrado hoy")
    expect(document.getElementById("routineList").textContent).toContain("Rutina matutina")
    expect(document.getElementById("routineList").textContent).toContain("María López")
    expect(document.querySelector("#routineList a").getAttribute("href")).toBe("./routines.html?older_adult_id=15")
  })

  it("mantiene contadores en cero y muestra el estado vacío", async () => {
    window.CuidadoApi = {
      fetchJson: vi.fn((path) => Promise.resolve(path === "/rutinas" ? { rutinas: [] } : {})),
    }

    await loadDashboard()

    expect(document.getElementById("olderAdultsCount").textContent).toBe("0")
    expect(document.getElementById("medicineCount").textContent).toBe("0")
    expect(document.getElementById("routineList").textContent).toContain("Todavía no hay cambios registrados en rutinas.")
  })

  it("presenta mensajes seguros cuando falla la API", async () => {
    window.CuidadoApi = {
      fetchJson: vi.fn().mockRejectedValue(new Error("No se pudo cargar la información.")),
    }

    await loadDashboard()

    expect(document.querySelector(".medicine-text").textContent).toBe("No se pudo cargar el estado de hoy")
    expect(document.getElementById("routineList").textContent).toContain("No se pudo cargar la información.")
  })
})
