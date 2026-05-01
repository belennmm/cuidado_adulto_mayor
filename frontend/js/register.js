const form = document.querySelector(".register")
const registerMessage = document.getElementById("registerMessage")
const passwordInput = document.getElementById("password")
const togglePassword = document.getElementById("togglePassword")
const togglePasswordIcon = togglePassword ? togglePassword.querySelector("i") : null

const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

function showMessage(message, isError = false) {
    if (registerMessage) {
        registerMessage.textContent = message
        registerMessage.classList.toggle("error", isError)
    }
}

function getErrorMessage(data, fallback) {
    if (data?.message) return data.message

    if (data?.errors) {
        const firstError = Object.values(data.errors).flat()[0]
        if (firstError) return firstError
    }

    return fallback
}

function clearSession() {
    localStorage.removeItem("token")
    localStorage.removeItem("user")
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
        const response = await fetch(`${API_URL}/register`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                name,
                email,
                password,
                role,
                location,
                phone,
                birthdate
            })
        })

        const data = await response.json()

        if (!response.ok) {
            clearSession()
            console.log(data)
            showMessage(getErrorMessage(data, "Error al registrar"), true)
            return
        }

        clearSession()
        showMessage(data.message || "Registro enviado. Un administrador debe aprobar tu cuenta antes de iniciar sesion.")

        setTimeout(() => {
            navigateTo("../index.html")
        }, 2500)

    } catch (error) {
        console.error(error)
        showMessage("No se pudo conectar con el servidor", true)
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
