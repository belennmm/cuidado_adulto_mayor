(() => {
  const form = document.getElementById("revokeSessionsForm")
  if (!form) return
  const password = document.getElementById("revokeSessionsPassword")
  const message = document.getElementById("revokeSessionsMessage")
  const button = form.querySelector("button[type=submit]")
  form.addEventListener("submit", async event => {
    event.preventDefault()
    if (button.disabled) return
    if (!password.value) { message.textContent = "Escribe tu contraseña actual."; return }
    button.disabled = true
    message.textContent = "Cerrando sesiones..."
    try {
      await window.CuidadoApi.fetchJson("/logout-all", { method: "POST", body: JSON.stringify({ current_password: password.value }) })
      window.AuthSession.clearSession()
      password.value = ""
      if (window.navigateWithLoading) window.navigateWithLoading("../../index.html")
      else window.location.assign("../../index.html")
    } catch (error) {
      password.value = ""
      message.textContent = error.message || "No se pudieron cerrar las sesiones. Intenta nuevamente."
      button.disabled = false
    }
  })
})()
