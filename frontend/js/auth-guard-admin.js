(() => {
  const allowedRoles = ["admin", "administrador"]

  function hidePageUntilValidated() {
    document.documentElement.style.visibility = "hidden"
  }

  function showPage() {
    document.documentElement.style.visibility = ""
  }

  function getUser() {
    return window.AuthSession?.getUser(allowedRoles) || null
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

  function validateAdminAccess() {
    const token = window.AuthSession?.getToken(allowedRoles) || ""
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
    if (!allowedRoles.includes(role)) {
      redirectByRole(role)
      return
    }

    showPage()
  }

  hidePageUntilValidated()
  validateAdminAccess()
})()
