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

  const list = document.getElementById("accountSessionsList")
  const listMessage = document.getElementById("accountSessionsMessage")
  function exit() {
    window.AuthSession?.clearSession()
    if (window.navigateWithLoading) window.navigateWithLoading("../../index.html")
    else window.location.assign("../../index.html")
  }
  async function loadSessions() {
    if (!list) return
    listMessage.textContent = "Cargando sesiones..."
    try {
      const data = await window.CuidadoApi.fetchJson("/sessions")
      list.replaceChildren()
      for (const session of data.sessions) {
        const item = document.createElement("li")
        const details = document.createElement("p")
        details.textContent = `${session.current ? "Esta sesion" : "Otra sesion"} (${session.type}) - ${session.device} - IP: ${session.ip_address || "Desconocida"} - Ultima actividad: ${session.last_activity}`
        const close = document.createElement("button")
        close.type = "button"
        close.textContent = session.current ? "Cerrar esta sesion" : "Cerrar sesion"
        close.addEventListener("click", async () => {
          close.disabled = true
          try {
            const result = await window.CuidadoApi.fetchJson(`/sessions/${encodeURIComponent(session.id)}`, { method: "DELETE" })
            if (result.current) { exit(); return }
            await loadSessions()
          } catch (error) {
            listMessage.textContent = error.message
            close.disabled = false
          }
        })
        item.append(details, close)
        list.append(item)
      }
      listMessage.textContent = data.sessions.length ? "" : "No hay sesiones activas."
    } catch (error) { listMessage.textContent = error.message }
  }
  document.getElementById("refreshAccountSessions")?.addEventListener("click", loadSessions)
  const confirmForm = document.getElementById("reauthenticateForm")
  confirmForm?.addEventListener("submit", async event => {
    event.preventDefault()
    const button = confirmForm.querySelector("button")
    if (button.disabled) return
    button.disabled = true
    const input = document.getElementById("reauthenticatePassword")
    const status = document.getElementById("reauthenticateMessage")
    try {
      const data = await window.CuidadoApi.fetchJson("/reauthenticate", { method: "POST", body: JSON.stringify({ current_password: input.value }) })
      status.textContent = data.message
    } catch (error) { status.textContent = error.message }
    finally { input.value = ""; button.disabled = false }
  })
  loadSessions()
})()
