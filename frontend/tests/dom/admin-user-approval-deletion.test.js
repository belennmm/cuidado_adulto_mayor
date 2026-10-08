import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function renderEditForm() {
  document.body.innerHTML = `
    <form id="editUserForm">
      <select id="userType"><option value="cuidador_familiar">Familiar</option></select><input id="username"><input id="email"><input id="password">
      <input id="location"><input id="phone"><input id="birthdate"><select id="status"><option value="Activo">Activo</option></select>
      <button id="togglePassword" type="button"><i></i></button><button type="submit">Guardar</button>
    </form>
    <button id="openDeleteModal" type="button">Eliminar</button><div id="deleteModal"><button id="closeDeleteModal"></button><button id="confirmDeleteUser">Confirmar</button></div>
  `
}

describe("aprobación y eliminación de usuarios", () => {
  it("aprueba una solicitud pendiente desde el listado", async () => {
    document.body.innerHTML = '<input id="userSearchInput"><div id="usersTableBody"></div>'
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path, options = {}) => {
        if (options.method === "PATCH") return Promise.resolve({ message: "Usuario aprobado correctamente." })
        return Promise.resolve({ users: [{ id: 8, name: "Ana", role: "cuidador_familiar", email: "ana@example.test", is_approved: false }] })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/users.js")
    await flushRequests()
    document.querySelector(".approve-button").click()
    await flushRequests()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/users/8/approve", expect.objectContaining({ method: "PATCH" }))
    expect(window.showAdminAlert).toHaveBeenCalledWith("Usuario aprobado correctamente.", { variant: "success" })
  })

  it("elimina un usuario confirmado desde su formulario de edición", async () => {
    window.history.replaceState({}, "", "?id=12")
    renderEditForm()
    window.navigateWithLoading = vi.fn()
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path, options = {}) => {
        if (options.method === "DELETE") return Promise.resolve({ message: "Usuario eliminado correctamente." })
        return Promise.resolve({ user: { id: 12, name: "Luis", email: "luis@example.test", role: "cuidador_familiar", is_approved: true } })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/user-form.js")
    await import("../../js/admin/edit-user.js")
    await flushRequests()
    document.getElementById("openDeleteModal").click()
    expect(document.getElementById("deleteModal").classList.contains("active")).toBe(true)
    document.getElementById("confirmDeleteUser").click()
    await flushRequests()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/users/12", expect.objectContaining({ method: "DELETE" }))
    expect(window.navigateWithLoading).toHaveBeenCalledWith("./users.html")
  })
})
