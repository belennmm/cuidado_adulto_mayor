(() => {
  function safeJsonParse(value) {
    try {
      return JSON.parse(value)
    } catch {
      return null
    }
  }

  function getUser() {
    return safeJsonParse(JSON.stringify(window.AuthSession?.getUser() || null)) || {}
  }

  function saveUser(user) {
    if (!user) return
    window.AuthSession?.saveUser(user)
  }

  const escapeHtml = window.CuidadoUi.escapeHtml

  async function fetchJson(path, options = {}) {
    return window.CuidadoApi.fetchJson(path, {
      ...options,
      expectedRoles: ["familiar", "cuidador_familiar"],
      fallbackError: "No se pudo cargar la información.",
    })
  }

  const formatDate = window.CuidadoUi.formatLongDate
  const formatShortDate = window.CuidadoUi.formatShortDate
  const formatTime = window.CuidadoUi.formatTime
  const normalize = window.CuidadoUi.normalizeText

  function getStatusClass(status) {
    const normalized = normalize(status)
    if (normalized === "estable") return "status-stable"
    if (normalized === "atencion") return "status-attention"
    return "status-critical"
  }

  function getSeverityClass(severity) {
    const normalized = normalize(severity)
    if (normalized === "alta") return "severity-high"
    if (normalized === "baja") return "severity-low"
    return "severity-medium"
  }

  function getRoleLabel(role) {
    const normalized = normalize(role)
    if (normalized === "admin") return "Administrador"
    if (normalized === "profesional" || normalized === "cuidador_profesional") {
      return "Cuidador profesional"
    }

    return "Cuidador familiar"
  }

  function setText(id, value, fallback = "0") {
    const element = document.getElementById(id)
    if (!element) return
    element.textContent = value ?? fallback
  }

  function renderEmpty(message, icon = "bx bx-info-circle") {
    return `
      <div class="empty-card">
        <i class="${icon}"></i>
        <span>${escapeHtml(message)}</span>
      </div>
    `
  }

  async function showAlert(message, options = {}) {
    if (typeof window.showAdminAlert === "function") {
      await window.showAdminAlert(message, options)
      return
    }

    console.warn(message)
  }

  async function showConfirm(message, options = {}) {
    if (typeof window.showAdminConfirm === "function") {
      return window.showAdminConfirm(message, options)
    }

    console.warn(message, options)
    return false
  }

  window.FamilyCare = {
    fetchJson,
    formatDate,
    formatShortDate,
    formatTime,
    getRoleLabel,
    getSeverityClass,
    getStatusClass,
    getUser,
    normalize,
    renderEmpty,
    saveUser,
    setText,
    showAlert,
    showConfirm,
    escapeHtml,
  }
})()
