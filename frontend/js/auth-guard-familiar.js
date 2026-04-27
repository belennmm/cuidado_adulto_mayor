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
      cuidador_profesional: "../cuidador-profesional/home-page.html",
      cuidador_familiar: "./home-page.html",
      profesional: "../cuidador-profesional/home-page.html",
      familiar: "./home-page.html",
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

    const role = String(user.role || "").trim().toLowerCase()
    if (role !== "familiar" && role !== "cuidador_familiar") {
      redirectByRole(role)
    }
  })
})()
