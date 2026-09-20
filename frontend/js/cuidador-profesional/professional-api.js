(() => {
  const escapeHtml = window.CuidadoUi.escapeHtml

  async function fetchJson(path, options = {}) {
    return window.CuidadoApi.fetchJson(path, {
      ...options,
      expectedRoles: ["profesional", "cuidador_profesional"],
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

  function renderEmpty(message, icon = "bx bx-info-circle") {
    return `
      <div class="professional-empty">
        <i class="${icon}"></i>
        <span>${escapeHtml(message)}</span>
      </div>
    `
  }

  const setText = (id, value, fallback = "0") =>
    window.CuidadoUi.setText(id, value, fallback)

  window.ProfessionalCare = {
    escapeHtml,
    fetchJson,
    formatDate,
    formatShortDate,
    formatTime,
    getSeverityClass,
    getStatusClass,
    normalize,
    renderEmpty,
    setText,
  }
})()
