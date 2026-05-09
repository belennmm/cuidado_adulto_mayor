(() => {
  const api = window.ProfessionalCare

  function safeJsonParse(value) {
    try {
      return JSON.parse(value)
    } catch {
      return null
    }
  }

  function setText(id, value) {
    const el = document.getElementById(id)
    if (!el) return
    el.textContent = value || "-"
  }

  function getRoleLabel(role) {
    const labels = {
      cuidador_profesional: "Cuidador profesional",
      profesional: "Cuidador profesional",
      cuidador_familiar: "Cuidador familiar",
      familiar: "Cuidador familiar",
      admin: "Administrador",
    }

    return labels[role] || role || "-"
  }

  function renderUser(user) {
    setText("profileName", user?.name)
    setText("profileEmail", user?.email)
    setText("profileRole", getRoleLabel(user?.role))
  }

  async function loadUser() {
    const cachedUser = safeJsonParse(localStorage.getItem("user"))
    renderUser(cachedUser)

    if (!api?.fetchJson) return

    try {
      const data = await api.fetchJson("/me")
      const user = data.user || cachedUser

      if (user) {
        localStorage.setItem("user", JSON.stringify(user))
      }

      renderUser(user)
    } catch (error) {
      if (!cachedUser) {
        setText("profileName", error.message)
      }
    }
  }

  document.addEventListener("DOMContentLoaded", loadUser)
})()
