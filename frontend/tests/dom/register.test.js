import { fireEvent } from "@testing-library/dom"
import { beforeEach, describe, expect, it, vi } from "vitest"

describe("formulario de registro", () => {
  let fetchJson

  beforeEach(async () => {
    document.body.innerHTML = `
      <form class="register">
        <input id="username" /><input id="email" /><input id="password" type="password" />
        <select id="userType"><option value="">Seleccione</option><option value="familiar">Familiar</option></select>
        <input id="location" /><input id="phone" /><input id="birthdate" />
        <button type="submit">Registrar</button>
      </form>
      <button id="togglePassword"><i class="bx-hide"></i></button>
      <button id="goToLoginButton">Volver</button><p id="registerMessage"></p>`
    fetchJson = vi.fn()
    window.CuidadoApi = { fetchJson }
    window.AuthSession = { clearSession: vi.fn() }
    window.navigateWithLoading = vi.fn()
    await import("../../js/register.js")
  })

  it("valida los campos obligatorios antes de consumir la API", () => {
    fireEvent.submit(document.querySelector("form"))
    expect(document.getElementById("registerMessage").textContent).toBe("Completa los campos obligatorios")
    expect(document.getElementById("registerMessage").classList.contains("error")).toBe(true)
    expect(fetchJson).not.toHaveBeenCalled()
  })

  it("envía al backend los valores introducidos", async () => {
    fetchJson.mockResolvedValue({ message: "Registro enviado" })
    document.getElementById("username").value = "Laura Pérez"
    document.getElementById("email").value = "laura@test.com"
    document.getElementById("password").value = "password123"
    document.getElementById("userType").value = "familiar"

    fireEvent.submit(document.querySelector("form"))

    await vi.waitFor(() => expect(fetchJson).toHaveBeenCalledOnce())
    const [path, options] = fetchJson.mock.calls[0]
    expect(path).toBe("/register")
    expect(options).toMatchObject({ method: "POST", auth: false })
    expect(JSON.parse(options.body)).toMatchObject({
      name: "Laura Pérez", email: "laura@test.com", password: "password123", role: "familiar",
    })
    expect(document.getElementById("registerMessage").textContent).toBe("Registro enviado")
  })

  it("muestra los errores del registro y permite mostrar la contraseña", async () => {
    fetchJson.mockRejectedValue(new Error("El correo ya existe"))
    document.getElementById("username").value = "Laura"
    document.getElementById("email").value = "repetido@test.com"
    document.getElementById("password").value = "password123"
    document.getElementById("userType").value = "familiar"

    fireEvent.click(document.getElementById("togglePassword"))
    fireEvent.submit(document.querySelector("form"))

    expect(document.getElementById("password").type).toBe("text")
    await vi.waitFor(() => expect(document.getElementById("registerMessage").textContent).toBe("El correo ya existe"))
  })
})
