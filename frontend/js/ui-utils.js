(() => {
  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;")
  }

  function normalizeText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim()
  }

  function parseDateOnly(value) {
    if (!value) return null

    const [year, month, day] = String(value).split("-")
    if (!year || !month || !day) return null

    const date = new Date(Number(year), Number(month) - 1, Number(day))
    return Number.isNaN(date.getTime()) ? null : date
  }

  function formatLongDate(value, fallback = "Hoy") {
    const date = parseDateOnly(value)
    if (!date) return fallback

    return new Intl.DateTimeFormat("es-GT", {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    }).format(date)
  }

  function formatShortDate(value, fallback = "Sin fecha") {
    const date = parseDateOnly(value)
    if (!date) return fallback

    return new Intl.DateTimeFormat("es-GT", {
      day: "numeric",
      month: "short",
      year: "numeric",
    }).format(date)
  }

  function formatNumericDate(value, fallback = "Sin fecha") {
    const date = parseDateOnly(value)
    if (!date) return fallback

    const day = String(date.getDate()).padStart(2, "0")
    const month = String(date.getMonth() + 1).padStart(2, "0")
    return `${day}/${month}/${date.getFullYear()}`
  }

  function formatTime(value, fallback = "Sin hora") {
    return value ? String(value).slice(0, 5) : fallback
  }

  function getRoleLabel(role, fallback = "Sin rol") {
    const labels = {
      admin: "Administrador",
      administrador: "Administrador",
      profesional: "Cuidador Profesional",
      cuidador_profesional: "Cuidador Profesional",
      familiar: "Cuidador Familiar",
      cuidador_familiar: "Cuidador Familiar",
    }

    return labels[normalizeText(role)] || role || fallback
  }

  function setText(id, value, fallback = "") {
    const element = document.getElementById(id)
    if (!element) return null

    element.textContent = value ?? fallback
    return element
  }

  function setMessage(target, message, options = {}) {
    const element = typeof target === "string"
      ? document.getElementById(target)
      : target
    if (!element) return null

    const {
      type = "",
      errorClass = "is-error",
      successClass = null,
      successWhenMessage = false,
    } = options
    const hasMessage = Boolean(message)
    const isError = type === "error"
    const isSuccess = type === "success" || (successWhenMessage && hasMessage && !isError)

    element.textContent = message || ""
    if (errorClass) element.classList.toggle(errorClass, isError)
    if (successClass) element.classList.toggle(successClass, isSuccess)
    return element
  }

  window.CuidadoUi = Object.freeze({
    escapeHtml,
    formatLongDate,
    formatNumericDate,
    formatShortDate,
    formatTime,
    getRoleLabel,
    normalizeText,
    parseDateOnly,
    setMessage,
    setText,
  })
})()
