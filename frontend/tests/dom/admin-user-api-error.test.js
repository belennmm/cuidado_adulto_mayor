import { describe, expect, it, vi } from "vitest"

async function flushRequests() {
  await Promise.resolve()
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe("error de API al crear usuarios", () => {
  it("muestra el error del backend y restablece el formulario", async () => {
    document.body.innerHTML = `
      <form id="newUserForm">
        <select id="userType"><option value="cuidador_familiar" selected>Familiar</option></select>
        <input id="username" value="Ana Pérez"><input id="email" value="ana@example.test"><input id="password" value="secreto123">
        <input id="location"><input id="phone"><input id="birthdate"><button id="togglePassword" type="button"><i></i></button><button id="submitUser" type="submit">Guardar</button>
      </form><div id="requestList"></div>
    `
    window.showAdminAlert = vi.fn().mockResolvedValue()
    window.CuidadoApi = {
      getToken: vi.fn(() => "admin-token"),
      fetchJson: vi.fn((path, options = {}) => {
        if (options.method === "POST") return Promise.reject(new Error("No se pudo completar la solicitud."))
        return Promise.resolve({ users: [] })
      }),
    }

    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/admin/user-form.js")
    await import("../../js/admin/new-user.js")
    await flushRequests()
    document.getElementById("newUserForm").dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }))
    await flushRequests()

    expect(window.CuidadoApi.fetchJson).toHaveBeenCalledWith("/admin/users", expect.objectContaining({ method: "POST" }))
    expect(window.showAdminAlert).toHaveBeenCalledWith("No se pudo completar la solicitud.", { variant: "error" })
    expect(document.getElementById("submitUser").disabled).toBe(false)
  })
})
