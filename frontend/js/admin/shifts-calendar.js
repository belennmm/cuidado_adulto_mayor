(() => {
  function safeJsonParse(value) {
    try {
      return JSON.parse(value)
    } catch {
      return null
    }
  }

  function navigateToLogin() {
    if (window.navigateWithLoading) {
      window.navigateWithLoading("../../index.html")
      return
    }

    window.location.assign("../../index.html")
  }

  function renderBaseState() {
    const container = document.getElementById("shiftsCalendarContainer")
    if (!container) return

    container.innerHTML = `
      <div class="empty-state">
        El calendario de turnos estara disponible aqui.
      </div>
    `
  }

  document.addEventListener("DOMContentLoaded", () => {
    const token = localStorage.getItem("token")
    const user = safeJsonParse(localStorage.getItem("user"))
    const role = String(user?.role || "").trim().toLowerCase()

    if (!token || role !== "admin") {
      navigateToLogin()
      return
    }

    renderBaseState()
  })
})()
