const solicitarCuentaText = document.getElementById("solicitarCuentaText")
const passwordInput = document.getElementById("passwordInput")
const togglePassword = document.getElementById("togglePassword")
const loginForm = document.querySelector(".login-credentials") || document.querySelector(".rectangle-parent")
const usernameInput = document.querySelector(".ingrese-usuario")
let loginBusy = false
let mfaChallenge = null
const mfaForm = document.getElementById("loginMfaForm")
const loginMessage = document.getElementById("loginMessage")

function showLoginMessage(message) {
    if (loginMessage) {
        loginMessage.textContent = message
    }
}

async function showLoginPopup(message, options = {}) {
    showLoginMessage(message)

    if (window.showAdminAlert) {
        await window.showAdminAlert(message, options)
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
    const message = "No se encontró un acceso para este tipo de usuario"
    await showLoginPopup(message, { variant: "error" })
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
        if (loginBusy) return
        clearSession()
        mfaChallenge = null
        if (mfaForm) mfaForm.hidden = true

        const email = usernameInput ? usernameInput.value.trim() : ""
        const password = passwordInput ? passwordInput.value : ""

        if (!email || !password) {
            await showLoginPopup("Completa el correo electrónico y la contraseña", { variant: "error" })
            return
        }

        loginBusy = true
        try {
            const data = await window.CuidadoApi.fetchJson("/login", {
                method: "POST",
                auth: false,
                body: JSON.stringify({ email, password }),
                fallbackError: "Credenciales inválidas",
            })
            if (passwordInput) passwordInput.value = ""
            if (data.mfa_required) {
                mfaChallenge = data.challenge
                if (mfaForm) { mfaForm.hidden = false; mfaForm.elements.code.focus() }
                showLoginMessage("Introduce el codigo de tu autenticador o un codigo de recuperacion.")
                return
            }
            if (!window.AuthSession?.saveSession(data.user)) {
                await showLoginPopup("No se pudo guardar la sesión", { variant: "error" })
                return
            }

            await redirectByRole(data.user?.role || "familiar")

        } catch (error) {
            clearSession()
            await showLoginPopup(error.message || "No se pudo conectar con el servidor.", { variant: "error" })
        } finally { loginBusy = false; if (passwordInput) passwordInput.value = "" }
    })
}

if (mfaForm) mfaForm.addEventListener("submit", async event => {
    event.preventDefault()
    if (!mfaChallenge) return
    const button = mfaForm.querySelector("button")
    if (button.disabled) return
    button.disabled = true
    const code = mfaForm.elements.code.value.trim()
    mfaForm.elements.code.value = ""
    try {
        const data = await window.CuidadoApi.fetchJson("/mfa/verify", {
            method: "POST", auth: false, body: JSON.stringify({ challenge: mfaChallenge, code }),
            fallbackError: "Codigo no valido o expirado"
        })
        mfaChallenge = null
        mfaForm.hidden = true
        if (window.AuthSession?.saveSession(data.user)) await redirectByRole(data.user?.role)
    } catch (error) {
        showLoginMessage(error.message || "Codigo no valido o expirado. Vuelve a iniciar sesion.")
    } finally { button.disabled = false }
})
