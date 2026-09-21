import { beforeEach, describe, expect, it } from "vitest"

describe("CuidadoForms", () => {
  beforeEach(async () => {
    await import("../../js/form-utils.js")
  })

  it("lee campos, elimina espacios y convierte vacíos en null", () => {
    document.body.innerHTML = '<input id="name" value="  Ana  "><input id="phone" value="  ">'

    expect(window.CuidadoForms.readPayload({
      name: document.getElementById("name"),
      phone: document.getElementById("phone"),
    })).toEqual({ name: "Ana", phone: null })
  })

  it("lee valores desde FormData", () => {
    const formData = new FormData()
    formData.set("fullName", "  Luis Pérez  ")

    expect(window.CuidadoForms.readPayload({
      full_name: { formData, key: "fullName" },
    })).toEqual({ full_name: "Luis Pérez" })
  })

  it("identifica campos obligatorios ausentes", () => {
    expect(window.CuidadoForms.findMissing(
      { name: "Ana", email: null, role: "" },
      ["name", "email", "role"]
    )).toEqual(["email", "role"])
  })

  it("normaliza roles y estados de aprobación", () => {
    expect(window.CuidadoForms.normalizeRole("cuidador-profesional")).toBe("cuidador_profesional")
    expect(window.CuidadoForms.isApproved("t")).toBe(true)
    expect(window.CuidadoForms.isApproved(0)).toBe(false)
  })

  it("habilita y deshabilita controles de un formulario", () => {
    document.body.innerHTML = '<form id="form"><input><select></select><button></button></form>'
    const form = document.getElementById("form")

    window.CuidadoForms.setDisabled(form, true)
    expect([...form.elements].every((element) => element.disabled)).toBe(true)

    window.CuidadoForms.setDisabled(form, false)
    expect([...form.elements].every((element) => !element.disabled)).toBe(true)
  })
})
