import { fireEvent } from "@testing-library/dom"
import { beforeEach, describe, expect, it } from "vitest"

describe("AppPopup", () => {
  beforeEach(async () => {
    await import("../../js/app-popup-styles.js")
    await import("../../js/app-popup.js")
  })

  it("muestra y confirma una alerta", async () => {
    const result = window.showAppAlert("Guardado", { variant: "success" })
    const overlay = document.querySelector(".admin-popup-overlay")
    expect(overlay?.classList.contains("active")).toBe(true)
    expect(overlay?.textContent).toContain("Guardado")
    fireEvent.click(overlay.querySelector(".admin-popup-confirm"))
    await expect(result).resolves.toBe(true)
  })

  it("permite cancelar una confirmación", async () => {
    const result = window.showAppConfirm("Eliminar registro")
    const cancel = document.querySelector(".admin-popup-cancel")
    expect(cancel.hidden).toBe(false)
    fireEvent.click(cancel)
    await expect(result).resolves.toBe(false)
  })
})
