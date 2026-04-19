const form = document.querySelector(".register")

const API_URL = "http://127.0.0.1:8080/api"

form.addEventListener("submit", async(e) => {
    e.preventDefault()

    const name = document.getElementById("username").value.trim()
    const email = document.getElementById("email").value.trim()
    const password = document.getElementById("password").value.trim()
    const role = document.getElementById("userType").value
    const location = document.getElementById("location").value
    const phone = document.getElementById("phone").value.trim()
    const birthdate = document.getElementById("birthdate").value

    if (!name || !email || !password) {
        alert("Completa los campos obligatorios")
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
            console.log(data)
            alert(data.message || "Error al registrar")
            return
        }

        alert("Usuario registrado correctamente")

        window.location.href = "../index.html"

    } catch (error) {
        console.error(error)
        alert("No se pudo conectar con el servidor")
    }
})


const goToLogin = document.getElementById("goToLoginButton")

if (goToLogin) {
    goToLogin.addEventListener("click", () => {
        window.location.href = "../index.html"
    })
}