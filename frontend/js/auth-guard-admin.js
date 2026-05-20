(() => {
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
    const session = window.AuthSession?.getSession(["admin"])
    if (!session?.token || !session.user) {
      redirectToLogin()
      return
    }

    const role = String(session.user.role || "").trim().toLowerCase()
    if (role !== "admin") {
      redirectByRole(role)
    }
  })
})()
