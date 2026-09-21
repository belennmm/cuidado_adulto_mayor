import { beforeEach, describe, expect, it, vi } from "vitest"

describe("CareNotificationsView", () => {
  let dependencies
  let view

  beforeEach(async () => {
    document.body.innerHTML = '<div class="actions"></div>'
    dependencies = {
      escapeHtml: (value) => String(value ?? "").replaceAll("<", "&lt;").replaceAll(">", "&gt;"),
      readAlarmed: vi.fn(() => new Set()),
      writeAlarmed: vi.fn(),
      readSnoozed: vi.fn(() => ({})),
      writeSnoozed: vi.fn(),
      currentMinutes: vi.fn(() => 540),
      supportsNotifications: vi.fn(() => false),
      isSoundEnabled: vi.fn(() => true),
      setSoundEnabled: vi.fn(),
      playAlertSound: vi.fn(),
      stopAlarmSound: vi.fn(),
      playAlarmSound: vi.fn(),
    }
    await import("../../js/care-notifications-view.js")
    view = window.CareNotificationsView.create(dependencies)
  })

  it("monta y actualiza el centro de notificaciones", () => {
    const instance = { mountSelector: ".actions", centerId: "center", currentAlerts: [] }
    instance.center = view.mountCenter(instance)
    view.renderCenter(instance, [{ title: "Medicamento", body: "Tomar dosis", url: "./routine.html" }])
    expect(instance.center.querySelector(".care-bell-badge").textContent).toBe("1")
    expect(instance.center.querySelector(".care-notification-list").textContent).toContain("Tomar dosis")
    instance.center.querySelector(".care-bell-button").click()
    expect(instance.center.querySelector(".care-notification-panel").hidden).toBe(false)
  })

  it("activa una alarma de medicamento que ya está venciendo", () => {
    const instance = {
      role: "family",
      latestData: { date: "2026-09-21" },
      currentAlerts: [{ type: "medication", dueMinutes: 540, alarmKey: "alarm:1", body: "Losartán para Rosa" }],
    }
    view.checkDueAlarms(instance)
    expect(dependencies.writeAlarmed).toHaveBeenCalled()
    expect(dependencies.playAlarmSound).toHaveBeenCalledOnce()
    expect(document.querySelector(".care-alarm")?.textContent).toContain("Losartán")
  })

  it("deshabilita el botón cuando el navegador no soporta notificaciones", () => {
    const instance = { mountSelector: ".actions", buttonId: "notify", role: "family", seedCurrentData: vi.fn() }
    const button = view.mountButton(instance)
    expect(button.disabled).toBe(true)
    expect(button.textContent).toContain("No soportadas")
  })
})
