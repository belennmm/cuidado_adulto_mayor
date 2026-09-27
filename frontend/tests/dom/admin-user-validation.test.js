import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe("validaciones del formulario de usuarios", () => {
  it("impide crear un usuario cuando faltan los campos obligatorios", async () => {
    document.body.innerHTML = `
      <form id="newUserForm">
        <select id="userType"><option value="">Seleccione</option></select><input id="username"><input id="email"><input id="password">
        <input id="location"><input id="phone"><input id="birthdate"><button id="togglePassword" type="button"><i></i></button><button type="submit">Guardar</button>
      </form><div id="requestList"></div>
    `
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn().mockResolvedValue({ users: [] }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/user-form.js")
    await import("../../js/admin/new-user.js")
    await flushRequests()
    document.getElementById("newUserForm").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.showAdminAlert).toHaveBeenCalledWith("Completa tipo de usuario, nombre, correo y contraseña.", { variant: "error" })
    expect(window.CuidadoApi.fetchJson).not.toHaveBeenCalledWith("/admin/users", expect.objectContaining({ method: "POST" }))
  })
})
