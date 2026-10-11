(() => {
  document.documentElement.style.visibility = "hidden"
  const allowedRoles = ["admin", "administrador"]
  const paths = { admin: "admin", administrador: "admin", profesional: "cuidador-profesional", cuidador_profesional: "cuidador-profesional", familiar: "cuidador-familiar", cuidador_familiar: "cuidador-familiar" }
  function navigate(url) {
    if (window.navigateWithLoading) window.navigateWithLoading(url)
    else window.location.assign(url)
  }
  window.AuthSession.refresh().then(user => {
    if (!allowedRoles.includes(user.role)) {
      navigate(paths[user.role] ? `../${paths[user.role]}/home-page.html` : "../../index.html")
      return
    }
    document.documentElement.style.visibility = ""
  }).catch(() => navigate("../../index.html"))
})()
