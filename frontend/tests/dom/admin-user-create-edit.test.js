import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function renderUserForm(id) {
  document.body.innerHTML = `
    <form id="${id}">
      <select id="userType"><option value="">Seleccione</option><option value="cuidador_familiar">Familiar</option><option value="cuidador_profesional">Profesional</option></select>
      <input id="username"><input id="email"><input id="password" type="password"><input id="location"><input id="phone"><input id="birthdate" type="date">
      <select id="status"><option value="Pendiente">Pendiente</option><option value="Activo">Activo</option></select>
      <button id="togglePassword" type="button"><i class="bx bx-hide"></i></button><button type="submit">Guardar</button>
    </form>
  `
}

describe("creación y edición de usuarios", () => {
  it("envía un usuario nuevo válido", async () => {
    renderUserForm("newUserForm")
    document.body.insertAdjacentHTML("beforeend", '<div id="requestList"></div>')
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path, options = {}) => {
        if (options.method === "POST") return Promise.resolve({ message: "Usuario creado correctamente." })
        return Promise.resolve({ users: [] })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/user-form.js")
    await import("../../js/admin/new-user.js")
    await flushRequests()
    document.getElementById("userType").value = "cuidador_familiar"
    document.getElementById("username").value = "Ana Pérez"
    document.getElementById("email").value = "ana@example.test"
    document.getElementById("password").value = "secreto123"
    document.getElementById("newUserForm").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/users", expect.objectContaining({
      method: "POST", body: expect.stringContaining('"name":"Ana Pérez"'),
    }))
    expect(window.showAdminAlert).toHaveBeenCalledWith("Usuario creado correctamente.", { variant: "success" })
  })

  it("carga y actualiza un usuario existente", async () => {
    window.history.replaceState({}, "", "?id=12")
    renderUserForm("editUserForm")
    window.navigateWithLoading = vi.fn()
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path, options = {}) => {
        if (options.method === "PUT") return Promise.resolve({ message: "Usuario actualizado correctamente." })
        return Promise.resolve({ user: { id: 12, name: "Luis Díaz", email: "luis@example.test", role: "cuidador_profesional", is_approved: true } })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/user-form.js")
    await import("../../js/admin/edit-user.js")
    await flushRequests()
    expect(document.getElementById("username").value).toBe("Luis Díaz")
    document.getElementById("username").value = "Luis Gómez"
    document.getElementById("editUserForm").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/users/12", expect.objectContaining({
      method: "PUT", body: expect.stringContaining('"name":"Luis Gómez"'),
    }))
    expect(window.navigateWithLoading).toHaveBeenCalledWith("./users.html")
  })
})
