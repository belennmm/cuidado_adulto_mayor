const solicitarCuentaText = document.getElementById("solicitarCuentaText")
const passwordInput = document.getElementById("passwordInput")
const togglePassword = document.getElementById("togglePassword")
const loginForm = document.querySelector(".rectangle-parent")
const usernameInput = document.querySelector(".ingrese-usuario")

const roleRedirects = {
  admin: "./pages/admin/home-page.html",
  cuidador_profesional: "./pages/cuidador-profesional/home-page.html",
  cuidador_familiar: "./pages/cuidador-familiar/home-page.html"
}

function redirectByRole(role) {
  const destination = roleRedirects[role]

  if (destination) {
    window.location.href = destination
    return
  }

  alert("No se encontró acceso para este tipo de usuario")
}

function getMockRoleByUsername(username) {
  const normalizedUsername = username.trim().toLowerCase()

  const mockUsers = {
    admin: "admin",
    profesional: "cuidador_profesional",
    familiar: "cuidador_familiar"
  }

  return mockUsers[normalizedUsername] || null
}

if (solicitarCuentaText) {
  solicitarCuentaText.addEventListener("click", function () {
    window.location.href = "./pages/register.html"
  })
}

if (togglePassword && passwordInput) {
  togglePassword.addEventListener("click", function () {
    const isPassword = passwordInput.type === "password"

    passwordInput.type = isPassword ? "text" : "password"
    togglePassword.classList.toggle("bx-hide")
    togglePassword.classList.toggle("bx-show")
  })
}

if (loginForm) {
  loginForm.addEventListener("submit", function (event) {
    event.preventDefault()

    const username = usernameInput ? usernameInput.value : ""
    const password = passwordInput ? passwordInput.value : ""

    if (!username || !password) {
      alert("Completa usuario y contraseña")
      return
    }

    const mockRole = getMockRoleByUsername(username)

    if (!mockRole) {
      alert("Usuario no reconocido")
      return
    }

    redirectByRole(mockRole)
  })
}