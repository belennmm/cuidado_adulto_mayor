(() => {
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
    el.textContent = value ?? "—"
  }

  document.addEventListener("DOMContentLoaded", () => {
    const user = safeJsonParse(localStorage.getItem("user"))

    setText("profileName", user?.name || "—")
    setText("profileEmail", user?.email || "—")
    setText("profileRole", user?.role || "—")
  })
})()

