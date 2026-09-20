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
})
