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
    if (window.navigateWithLoading) {
      window.navigateWithLoading("../../index.html")
      return
    }

    window.location.assign("../../index.html")
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
      if (window.navigateWithLoading) {
        window.navigateWithLoading(destination)
        return
      }

      window.location.assign(destination)
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
