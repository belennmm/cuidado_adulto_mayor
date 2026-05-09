(() => {
  const apiUrl = `${window.location.protocol}//${window.location.hostname}:8080/api`

  function safeJsonParse(value) {
    try {
      return JSON.parse(value)
    } catch {
      return null
    }
  }

  function getToken() {
    return localStorage.getItem("token")
  }

  function getUser() {
    return safeJsonParse(localStorage.getItem("user")) || {}
  }

  function saveUser(user) {
    if (!user) return
    localStorage.setItem("user", JSON.stringify(user))
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;")
  }

  async function fetchJson(path, options = {}) {
    const token = getToken()
    const response = await fetch(`${apiUrl}${path}`, {
      cache: "no-store",
      ...options,
      headers: {
        Accept: "application/json",
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers || {}),
      },
    })

    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(data.message || "No se pudo cargar la informacion.")
    }

    return data
  }

  function formatDate(value) {
    if (!value) return "Hoy"
    const [year, month, day] = value.split("-")
    const date = new Date(Number(year), Number(month) - 1, Number(day))

    return new Intl.DateTimeFormat("es-GT", {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    }).format(date)
  }

  function formatShortDate(value) {
    if (!value) return "Sin fecha"
    const [year, month, day] = value.split("-")
    const date = new Date(Number(year), Number(month) - 1, Number(day))

    return new Intl.DateTimeFormat("es-GT", {
      day: "numeric",
      month: "short",
      year: "numeric",
    }).format(date)
  }

  function formatTime(value) {
    if (!value) return "Sin hora"
    return String(value).slice(0, 5)
  }

  function normalize(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim()
  }

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
