import { describe, expect, it, vi } from "vitest"

const payload = '\"><img src=x onerror="alert(1)">&amp;'

describe("listados administrativos con datos especiales", () => {
  it("renderiza usuarios sin interpretar nombres, correos ni IDs como HTML", async () => {
    document.body.innerHTML = '<input id="userSearchInput"><div id="usersTableBody"></div>'
    window.CuidadoApi = { fetchJson: vi.fn().mockResolvedValue({ users: [{ id: payload, name: payload, email: payload, role: "familiar", is_approved: true }] }) }
    window.navigateWithLoading = vi.fn()
    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/users.js")
    await new Promise((resolve) => setTimeout(resolve, 0))
    const list = document.getElementById("usersTableBody")
    expect(list.querySelector("img, [onerror]")).toBeNull()
    expect(list.querySelector(".user-name span").textContent).toBe(payload)
    const button = list.querySelector(".edit-button")
    expect(button.dataset.id).toBe(payload)
    button.click()
    const destination = new URL(window.navigateWithLoading.mock.calls[0][0], window.location.href)
    expect(destination.searchParams.get("id")).toBe(payload)
    expect([...destination.searchParams.keys()]).toEqual(["id"])
  })

  it("renderiza adultos y mantiene el ID dentro de un único parámetro URL", async () => {
    document.body.innerHTML = '<input id="olderAdultSearchInput"><div id="olderAdultsTableBody"></div>'
    window.CuidadoApi = { getToken: () => "token", fetchJson: vi.fn().mockResolvedValue({ older_adults: [{ id: payload, full_name: payload, room: payload, caregiver_family: payload, status: "Estable" }] }) }
    window.navigateWithLoading = vi.fn()
    await import("../../js/ui-utils.js")
    await import("../../js/admin/adultos-mayores.js")
    await new Promise((resolve) => setTimeout(resolve, 0))
    const list = document.getElementById("olderAdultsTableBody")
    expect(list.querySelector("img, [onerror]")).toBeNull()
    expect(list.querySelector(".older-adult-name span").textContent).toBe(payload)
    const button = list.querySelector(".routine-button")
    expect(button.dataset.id).toBe(payload)
    button.click()
    const destination = new URL(window.navigateWithLoading.mock.calls[0][0], window.location.href)
    expect(destination.searchParams.get("older_adult_id")).toBe(payload)
    expect([...destination.searchParams.keys()]).toEqual(["older_adult_id"])
  })

  it("conserva el formulario y los eventos de cambios de turno profesional", async () => {
    document.body.innerHTML = '<div id="professionalSchedulesList"></div>'
    window.ProfessionalCare = {
      formatTime: (value) => String(value || "").slice(0, 5),
      fetchJson: vi.fn().mockResolvedValue({ schedules: [{ id: payload, day_of_week: 1, start_time: "09:00", end_time: "17:00", notes: payload }] }),
    }
    await import("../../js/ui-utils.js")
    await import("../../js/cuidador-profesional/shift.js")
    document.dispatchEvent(new Event("DOMContentLoaded"))
    await new Promise((resolve) => setTimeout(resolve, 0))
    const list = document.getElementById("professionalSchedulesList")
    expect(list.querySelector("img, [onerror]")).toBeNull()
    const form = list.querySelector("form")
    expect(form.dataset.id).toBe(payload)
    expect(form.elements.notes.value).toBe(payload)
    expect(form.elements.start_time.value).toBe("09:00")
    expect(form.hidden).toBe(true)
    list.querySelector(".schedule-change-toggle").click()
    expect(form.hidden).toBe(false)
    form.querySelector(".schedule-change-cancel").click()
    expect(form.hidden).toBe(true)
  })
})
