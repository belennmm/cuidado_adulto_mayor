(() => {
  function safeJsonParse(value) {
    try {
      return JSON.parse(value)
    } catch {
      return null
    }
  }

  function getUser() {
    return safeJsonParse(localStorage.getItem("user"))
  }

  function redirectToLogin() {
    window.location.href = "../../index.html"
  }

  function redirectByRole(role) {
    const normalized = String(role || "").trim().toLowerCase()

    const roleRedirects = {
      admin: "../admin/home-page.html",
      cuidador_profesional: "./home-page.html",
      cuidador_familiar: "../cuidador-familiar/home-page.html",
      profesional: "./home-page.html",
      familiar: "../cuidador-familiar/home-page.html",
    }

    const destination = roleRedirects[normalized]
    if (destination) {
      window.location.href = destination
      return
    }

    redirectToLogin()
  }

  document.addEventListener("DOMContentLoaded", () => {
    const token = localStorage.getItem("token")
    if (!token) {
      redirectToLogin()
      return
    }

    const user = getUser()
    if (!user) {
      redirectToLogin()
      return
    }

    const role = user.role
    if (String(role || "").trim().toLowerCase() !== "profesional") {
      redirectByRole(role)
    }
  })
})()

