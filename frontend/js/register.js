const form = document.querySelector(".register")
const registerMessage = document.getElementById("registerMessage")
const passwordInput = document.getElementById("password")
const togglePassword = document.getElementById("togglePassword")
const togglePasswordIcon = togglePassword ? togglePassword.querySelector("i") : null

function showMessage(message, isError = false) {
    if (registerMessage) {
        registerMessage.textContent = message
        registerMessage.classList.toggle("error", isError)
    }
}

function clearSession() {
    window.AuthSession?.clearSession()
}

function navigateTo(url) {
    if (window.navigateWithLoading) {
        window.navigateWithLoading(url)
        return
    }

    window.location.assign(url)
}

form.addEventListener("submit", async(e) => {
    e.preventDefault()
    clearSession()

    const name = document.getElementById("username").value.trim()
    const email = document.getElementById("email").value.trim()
    const password = document.getElementById("password").value.trim()
    const role = document.getElementById("userType").value
    const location = document.getElementById("location").value
    const phone = document.getElementById("phone").value.trim()
    const birthdate = document.getElementById("birthdate").value

    if (!name || !email || !password) {
        showMessage("Completa los campos obligatorios", true)
        return
    }

    if (!role) {
        showMessage("Selecciona el tipo de usuario", true)
        return
    }

    try {
        const data = await window.CuidadoApi.fetchJson("/register", {
            method: "POST",
            auth: false,
            body: JSON.stringify({
                name,
                email,
                password,
                role,
                location,
                phone,
                birthdate
            }),
            fallbackError: "Error al registrar",
        })

        clearSession()
        showMessage(data.message || "Registro enviado. Un administrador debe aprobar tu cuenta antes de iniciar sesion.")

        setTimeout(() => {
            navigateTo("../index.html")
        }, 2500)

    } catch (error) {
        console.error(error)
        showMessage(error.message || "No se pudo conectar con el servidor", true)
    }
})


const goToLogin = document.getElementById("goToLoginButton")

if (goToLogin) {
    goToLogin.addEventListener("click", () => {
        navigateTo("../index.html")
    })
}

if (togglePassword && passwordInput) {
    togglePassword.addEventListener("click", () => {
        const isPassword = passwordInput.type === "password"
        passwordInput.type = isPassword ? "text" : "password"

        if (togglePasswordIcon) {
            togglePasswordIcon.classList.toggle("bx-hide", !isPassword)
            togglePasswordIcon.classList.toggle("bx-show", isPassword)
        }
    })
}
