import { beforeEach, describe, expect, it } from "vitest"

describe("CareNotificationsCore", () => {
  beforeEach(async () => {
    localStorage.clear()
    await import("../../js/care-notifications-core.js")
  })

  it("normaliza horarios de 12 y 24 horas", () => {
    expect(window.CareNotificationsCore.parseScheduleTimes("8:15 AM, 2:30 PM")).toEqual([
      { label: "08:15", minutes: 495 },
      { label: "14:30", minutes: 870 },
    ])
  })

  it("crea alertas solo para medicamentos pendientes", () => {
    const result = window.CareNotificationsCore.collectAlerts({
      date: "2026-09-20",
      incidents: [],
      next_medications: [
        { id: 1, medication_name: "A", older_adult_name: "Ana", schedule: "09:00", administered_today: false },
        { id: 2, medication_name: "B", schedule: "10:00", administered_today: true },
      ],
    })

    expect(result.alerts).toHaveLength(1)
    expect(result.alerts[0]).toMatchObject({ dueMinutes: 540, type: "medication" })
  })

  it("persiste las alertas vistas por rol y fecha", () => {
    window.CareNotificationsCore.writeSeen("family", "2026-09-20", new Set(["alert:1"]))
    expect([...window.CareNotificationsCore.readSeen("family", "2026-09-20")]).toEqual(["alert:1"])
  })
})
