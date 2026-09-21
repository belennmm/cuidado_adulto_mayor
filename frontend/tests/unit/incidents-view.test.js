import { beforeEach, describe, expect, it } from "vitest"

describe("IncidentsView", () => {
  let incidentsList
  let incidentsCount
  let view

  beforeEach(async () => {
    document.body.innerHTML = '<span id="count"></span><div id="list"></div>'
    incidentsList = document.getElementById("list")
    incidentsCount = document.getElementById("count")
    await import("../../js/incidents-view.js")
    view = window.IncidentsView.create({
      incidentsList,
      incidentsCount,
      escapeHtml: (value) => String(value ?? "").replaceAll("<", "&lt;").replaceAll(">", "&gt;"),
      formatDate: (value) => value,
      formatTime: (value) => String(value).slice(0, 5),
    })
  })

  it("muestra un estado vacío y contador cero", () => {
    view.renderIncidents([])
    expect(incidentsCount.textContent).toBe("0")
    expect(incidentsList.textContent).toContain("No hay incidentes registrados")
  })

  it("renderiza severidad, hora y datos del incidente", () => {
    view.renderIncidents([{ title: "Caída", status: "abierto", severity: "alta", incident_time: "14:35:00", description: "Sin lesión", adult_name: "Rosa", reported_by: "Ana" }])
    expect(incidentsCount.textContent).toBe("1")
    expect(incidentsList.querySelector(".incident-badge-high")?.textContent).toContain("Alta")
    expect(incidentsList.textContent).toContain("14:35")
    expect(incidentsList.textContent).toContain("Rosa")
  })
})
