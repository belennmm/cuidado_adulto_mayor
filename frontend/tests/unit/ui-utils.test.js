import { beforeEach, describe, expect, it } from "vitest"

describe("CuidadoUi", () => {
  beforeEach(async () => {
    await import("../../js/ui-utils.js")
  })

  it("escapa caracteres con significado HTML", () => {
    expect(window.CuidadoUi.escapeHtml(`<a href="x">O'Reilly & Co.</a>`))
      .toBe("&lt;a href=&quot;x&quot;&gt;O&#039;Reilly &amp; Co.&lt;/a&gt;")
  })

  it("normaliza texto con tildes y espacios", () => {
    expect(window.CuidadoUi.normalizeText("  Atención  ")).toBe("atencion")
  })

  it("formatea fechas sin desplazamientos de zona horaria", () => {
    expect(window.CuidadoUi.formatLongDate("2026-09-20")).toContain("20 de septiembre de 2026")
    expect(window.CuidadoUi.formatShortDate("2026-09-20")).toContain("20 sept 2026")
    expect(window.CuidadoUi.formatNumericDate("2026-09-20")).toBe("20/09/2026")
  })

  it("formatea horas y conserva valores alternativos", () => {
    expect(window.CuidadoUi.formatTime("08:30:00")).toBe("08:30")
    expect(window.CuidadoUi.formatTime(null)).toBe("Sin hora")
  })

  it("convierte roles conocidos en etiquetas", () => {
    expect(window.CuidadoUi.getRoleLabel("cuidador_profesional")).toBe("Cuidador Profesional")
    expect(window.CuidadoUi.getRoleLabel("familiar")).toBe("Cuidador Familiar")
  })

  it("actualiza texto conservando ceros y valores alternativos", () => {
    document.body.innerHTML = '<span id="total"></span>'

    expect(window.CuidadoUi.setText("total", 0)?.textContent).toBe("0")
    expect(window.CuidadoUi.setText("total", null, "Sin datos")?.textContent).toBe("Sin datos")
    expect(window.CuidadoUi.setText("inexistente", "texto")).toBeNull()
  })

  it("presenta mensajes y actualiza sus clases de estado", () => {
    document.body.innerHTML = '<p id="message"></p>'

    const message = window.CuidadoUi.setMessage("message", "No se pudo guardar", {
      type: "error",
      errorClass: "error",
      successClass: "success",
    })
    expect(message?.textContent).toBe("No se pudo guardar")
    expect(message?.classList.contains("error")).toBe(true)

    window.CuidadoUi.setMessage(message, "Guardado", {
      type: "success",
      errorClass: "error",
      successClass: "success",
    })
    expect(message?.classList.contains("error")).toBe(false)
    expect(message?.classList.contains("success")).toBe(true)
  })
})
