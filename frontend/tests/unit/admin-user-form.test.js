import { beforeEach, describe, expect, it } from "vitest"

describe("AdminUserForm", () => {
  let form
  let fields
  let togglePassword
  let userForm

  beforeEach(async () => {
    document.body.innerHTML = `
      <form id="userForm">
        <input id="name">
        <input id="email">
        <input id="password" type="password">
        <select id="role">
          <option value="cuidador-profesional">Profesional</option>
          <option value="admin">Administrador</option>
        </select>
        <input id="location">
        <input id="phone">
        <input id="birthdate">
        <select id="status"><option>Pendiente</option><option>Activo</option></select>
        <button id="togglePassword" type="button"><i class="bx bx-hide"></i></button>
      </form>
    `
    await import("../../js/form-utils.js")
    await import("../../js/admin/user-form.js")
    form = document.getElementById("userForm")
    togglePassword = document.getElementById("togglePassword")
    fields = {
      name: document.getElementById("name"),
      email: document.getElementById("email"),
      password: document.getElementById("password"),
      role: document.getElementById("role"),
      location: document.getElementById("location"),
      phone: document.getElementById("phone"),
      birthdate: document.getElementById("birthdate"),
      status: document.getElementById("status"),
    }
    userForm = window.AdminUserForm.create({ form, fields, togglePassword })
  })

  it("construye y normaliza el payload para crear usuarios", () => {
    fields.name.value = " Ana "
    fields.email.value = " ana@example.test "
    fields.password.value = " secreto "
    fields.role.value = "cuidador-profesional"

    expect(userForm.readPayload({ includePassword: true })).toMatchObject({
      name: "Ana",
      email: "ana@example.test",
      password: "secreto",
      role: "cuidador_profesional",
    })
  })

  it("omite la contraseña vacía al editar", () => {
    fields.name.value = "Ana"
    fields.email.value = "ana@example.test"
    fields.password.value = ""
    fields.status.value = "Activo"
    const payload = userForm.readPayload({ includeApproval: true })

    expect(payload.password).toBeUndefined()
    expect(payload.is_approved).toBe(true)
  })

  it("llena el formulario y bloquea el estado de administradores", () => {
    userForm.fill({ role: "admin", name: "Admin", email: "admin@example.test", birthdate: "1990-05-03T00:00:00", is_approved: false })
    expect(fields.name.value).toBe("Admin")
    expect(fields.birthdate.value).toBe("1990-05-03")
    expect(fields.status.value).toBe("Activo")
    expect(fields.status.disabled).toBe(true)
  })

  it("comparte el control para mostrar y ocultar contraseña", () => {
    userForm.bindPasswordToggle()
    togglePassword.click()
    expect(fields.password.type).toBe("text")
    expect(togglePassword.querySelector("i").classList.contains("bx-show")).toBe(true)
    userForm.reset()
    expect(fields.password.type).toBe("password")
    expect(togglePassword.querySelector("i").classList.contains("bx-hide")).toBe(true)
  })

  it("sincroniza el estado cuando cambia el rol", () => {
    userForm.bindAdminStatus()
    fields.role.value = "admin"
    fields.role.dispatchEvent(new Event("change"))
    expect(fields.status.value).toBe("Activo")
    expect(fields.status.disabled).toBe(true)
  })
})
