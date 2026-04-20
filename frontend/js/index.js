const solicitarCuentaText = document.getElementById("solicitarCuentaText")
const passwordInput = document.getElementById("passwordInput")
const togglePassword = document.getElementById("togglePassword")
const loginForm = document.querySelector(".rectangle-parent")
const usernameInput = document.querySelector(".ingrese-usuario")
const loginMessage = document.getElementById("loginMessage")

const API_URL = "http://127.0.0.1:8080/api"

function showLoginMessage(message) {
    if (loginMessage) {
        loginMessage.textContent = message
    }

    alert(message)
}

function clearSession() {
    localStorage.removeItem("token")
    localStorage.removeItem("user")
}

const roleRedirects = {
    admin: "./pages/admin/home-page.html",
    cuidador_profesional: "./pages/cuidador-profesional/home-page.html",
    cuidador_familiar: "./pages/cuidador-familiar/home-page.html",
    profesional: "./pages/cuidador-profesional/home-page.html",
    familiar: "./pages/cuidador-familiar/home-page.html"
}

function redirectByRole(role) {
    const destination = roleRedirects[role]
    if (destination) {
        window.location.href = destination
        return
    }
    alert("No se encontró acceso para este tipo de usuario")
}

if (solicitarCuentaText) {
    solicitarCuentaText.addEventListener("click", function() {
        window.location.href = "./pages/register.html"
    })
}

if (togglePassword && passwordInput) {
    togglePassword.addEventListener("click", function() {
        const isPassword = passwordInput.type === "password"
        passwordInput.type = isPassword ? "text" : "password"
        togglePassword.classList.toggle("bx-hide")
        togglePassword.classList.toggle("bx-show")
    })
}

if (loginForm) {
    loginForm.addEventListener("submit", async function(event) {
        event.preventDefault()
        clearSession()

        const email = usernameInput ? usernameInput.value.trim() : ""
        const password = passwordInput ? passwordInput.value.trim() : ""

        if (!email || !password) {
            showLoginMessage("Completa usuario y contraseña")
            return
        }

        try {
            const response = await fetch(`${API_URL}/login`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({ email, password })
            })

            const data = await response.json()

            if (!response.ok) {
                clearSession()
                showLoginMessage(data.message || "Credenciales inválidas")
                return
            }

            localStorage.setItem("token", data.token)
            localStorage.setItem("user", JSON.stringify(data.user))

            redirectByRole(data.user?.role || "familiar")

        } catch (error) {
            console.error("Error al conectar con el servidor:", error)
            clearSession()
            showLoginMessage("No se pudo conectar con el servidor.")
        }
    })
}
