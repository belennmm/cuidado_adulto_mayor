(() => {
  function getUser() {
    return window.AuthSession?.getUser(["admin", "administrador"]) || null
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
      admin: "./home-page.html",
      administrador: "./home-page.html",
      cuidador_profesional: "../cuidador-profesional/home-page.html",
      cuidador_familiar: "../cuidador-familiar/home-page.html",
      profesional: "../cuidador-profesional/home-page.html",
      familiar: "../cuidador-familiar/home-page.html",
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
    const token = window.AuthSession?.getToken(["admin", "administrador"]) || ""
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
    if (role !== "admin" && role !== "administrador") {
      redirectByRole(role)
    }
  })
})()
