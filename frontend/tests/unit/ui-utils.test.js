import { beforeEach, describe, expect, it, vi } from "vitest"

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

  it("crea celdas responsivas y colecciones con un estado vacío común", () => {
    document.body.innerHTML = '<section id="list"></section>'
    const target = document.getElementById("list")
    const rows = [{ name: "Ana" }, { name: "Luis" }]

    expect(window.CuidadoUi.renderCollection(target, rows, (row) =>
      window.CuidadoUi.cell("Nombre", row.name, [], "shared-cell"), "Sin resultados")).toBe(true)
    expect([...target.children].map((node) => node.textContent)).toEqual(["Ana", "Luis"])
    expect(target.firstElementChild.dataset.label).toBe("Nombre")

    expect(window.CuidadoUi.renderCollection(target, [], () => null, "Sin resultados")).toBe(false)
    expect(target.textContent).toBe("Sin resultados")
    expect(target.firstElementChild.className).toBe("empty-state")
  })

  it("centraliza alertas y confirmaciones", async () => {
    window.showAppAlert = vi.fn().mockResolvedValue(true)
    window.showAppConfirm = vi.fn().mockResolvedValue(true)

    await expect(window.CuidadoUi.notify("Guardado", { variant: "success" })).resolves.toBe(true)
    await expect(window.CuidadoUi.confirm("¿Continuar?")).resolves.toBe(true)
    expect(window.showAppAlert).toHaveBeenCalledWith("Guardado", { variant: "success" })
    expect(window.showAppConfirm).toHaveBeenCalledWith("¿Continuar?", {})
  })
})
