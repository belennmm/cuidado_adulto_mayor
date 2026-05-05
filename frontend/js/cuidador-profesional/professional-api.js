(() => {
  const apiUrl = `${window.location.protocol}//${window.location.hostname}:8080/api`

  function getToken() {
    return localStorage.getItem("token")
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

  function renderEmpty(message, icon = "bx bx-info-circle") {
    return `
      <div class="professional-empty">
        <i class="${icon}"></i>
        <span>${escapeHtml(message)}</span>
      </div>
    `
  }

  function setText(id, value, fallback = "0") {
    const element = document.getElementById(id)
    if (!element) return
    element.textContent = value ?? fallback
  }

  window.ProfessionalCare = {
    escapeHtml,
    fetchJson,
    formatDate,
    formatTime,
    getSeverityClass,
    getStatusClass,
    normalize,
    renderEmpty,
    setText,
  }
})()
