const solicitarCuentaText = document.getElementById("solicitarCuentaText")
const passwordInput = document.getElementById("passwordInput")
const togglePassword = document.getElementById("togglePassword")
const loginForm = document.querySelector(".rectangle-parent")
const usernameInput = document.querySelector(".ingrese-usuario")
const loginMessage = document.getElementById("loginMessage")

function showLoginMessage(message) {
    if (loginMessage) {
        loginMessage.textContent = message
    }
}

function clearSession() {
    window.AuthSession?.clearSession()
}

const roleRedirects = {
    admin: "./pages/admin/home-page.html",
    cuidador_profesional: "./pages/cuidador-profesional/home-page.html",
    cuidador_familiar: "./pages/cuidador-familiar/home-page.html",
    profesional: "./pages/cuidador-profesional/home-page.html",
    familiar: "./pages/cuidador-familiar/home-page.html"
}

async function redirectByRole(role) {
    const destination = roleRedirects[role]
    if (destination) {
        if (window.navigateWithLoading) {
            window.navigateWithLoading(destination)
            return
        }

        window.location.assign(destination)
        return
    }
    const message = "No se encontró acceso para este tipo de usuario"
    showLoginMessage(message)

    if (window.showAdminAlert) {
        await window.showAdminAlert(message, { variant: "error" })
    }
}

if (solicitarCuentaText) {
    solicitarCuentaText.addEventListener("click", function() {
        if (window.navigateWithLoading) {
            window.navigateWithLoading("./pages/register.html")
            return
        }

        window.location.assign("./pages/register.html")
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
            const data = await window.CuidadoApi.fetchJson("/login", {
                method: "POST",
                auth: false,
                body: JSON.stringify({ email, password }),
                fallbackError: "Credenciales invalidas",
            })
            if (!window.AuthSession?.saveSession(data.token, data.user)) {
                showLoginMessage("No se pudo guardar la sesion")
                return
            }

            await redirectByRole(data.user?.role || "familiar")

        } catch (error) {
            console.error("Error al conectar con el servidor:", error)
            clearSession()
            showLoginMessage(error.message || "No se pudo conectar con el servidor.")
        }
    })
}
