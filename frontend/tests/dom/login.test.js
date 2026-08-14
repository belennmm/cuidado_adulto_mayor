import { fireEvent, getByRole } from "@testing-library/dom"
import { beforeEach, describe, expect, it, vi } from "vitest"

describe("formulario de inicio de sesión", () => {
  let fetchJson
  let saveSession

  beforeEach(async () => {
    document.body.innerHTML = `
      <form class="rectangle-parent">
        <input class="ingrese-usuario" />
        <input id="passwordInput" type="password" />
        <button type="button" id="togglePassword" class="bx-hide">Mostrar</button>
        <button type="submit">Ingresar</button>
        <p id="loginMessage"></p>
      </form>
      <button id="solicitarCuentaText">Solicitar cuenta</button>`
    fetchJson = vi.fn()
    saveSession = vi.fn(() => true)
    window.CuidadoApi = { fetchJson }
    window.AuthSession = { clearSession: vi.fn(), saveSession }
    window.navigateWithLoading = vi.fn()
    await import("../../js/index.js")
  })

  it("muestra un mensaje y no llama la API cuando faltan credenciales", async () => {
    fireEvent.submit(document.querySelector("form"))
    await vi.waitFor(() => expect(document.getElementById("loginMessage").textContent).toContain("Completa el correo electrónico"))
    expect(fetchJson).not.toHaveBeenCalled()
  })

  it("alterna la visibilidad de la contraseña al hacer click", () => {
    fireEvent.click(document.getElementById("togglePassword"))
    expect(document.getElementById("passwordInput").type).toBe("text")
  })

  it("envía credenciales, guarda la sesión y redirige según el rol", async () => {
    fetchJson.mockResolvedValue({ token: "token", user: { id: 1, role: "admin" } })
    document.querySelector(".ingrese-usuario").value = "admin@test.com"
    document.getElementById("passwordInput").value = "password123"

    fireEvent.click(getByRole(document.body, "button", { name: "Ingresar" }))

    await vi.waitFor(() => expect(fetchJson).toHaveBeenCalledWith("/login", expect.objectContaining({ method: "POST", auth: false })))
    expect(saveSession).toHaveBeenCalledWith("token", { id: 1, role: "admin" })
    expect(window.navigateWithLoading).toHaveBeenCalledWith("./pages/admin/home-page.html")
  })

  it("muestra el error devuelto por la API", async () => {
    fetchJson.mockRejectedValue(new Error("Credenciales invalidas"))
    document.querySelector(".ingrese-usuario").value = "user@test.com"
    document.getElementById("passwordInput").value = "incorrecta"

    fireEvent.submit(document.querySelector("form"))

    await vi.waitFor(() => expect(document.getElementById("loginMessage").textContent).toBe("Credenciales invalidas"))
  })
})
