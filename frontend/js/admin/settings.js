(() => {
  const STORAGE_KEY = "adminPanelSettings"

  const defaultSettings = {
    defaultPage: "./dashboard.html",
    showLoading: true,
    dailySummary: true,
    careAlerts: true,
  }

  function getToken() {
    return (window.AuthSession?.getToken() || "")
  }

  function setText(id, value) {
    const element = document.getElementById(id)
    if (element) element.textContent = value ?? ""
  }

  function setMessage(message, isError = false) {
    const element = document.getElementById("adminSettingsMessage")
    if (!element) return

    element.textContent = message || ""
    element.classList.toggle("is-error", isError)
  }

  function loadSettings() {
    try {
      return {
        ...defaultSettings,
        ...JSON.parse(localStorage.getItem(STORAGE_KEY) || "{}"),
      }
    } catch (error) {
      return { ...defaultSettings }
    }
  }

  function saveSettings(settings) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(settings))
  }

  function applySettingsToForm(settings) {
    const defaultPage = document.getElementById("settingsDefaultPage")
    const showLoading = document.getElementById("settingsShowLoading")
    const dailySummary = document.getElementById("settingsDailySummary")
    const careAlerts = document.getElementById("settingsCareAlerts")

    if (defaultPage) defaultPage.value = settings.defaultPage
    if (showLoading) showLoading.checked = Boolean(settings.showLoading)
    if (dailySummary) dailySummary.checked = Boolean(settings.dailySummary)
    if (careAlerts) careAlerts.checked = Boolean(settings.careAlerts)
  }

  function readSettingsFromForm() {
    return {
      defaultPage: document.getElementById("settingsDefaultPage")?.value || defaultSettings.defaultPage,
      showLoading: Boolean(document.getElementById("settingsShowLoading")?.checked),
      dailySummary: Boolean(document.getElementById("settingsDailySummary")?.checked),
      careAlerts: Boolean(document.getElementById("settingsCareAlerts")?.checked),
    }
  }

  async function fetchJson(path) {
    const token = getToken()

    if (!token) {
      throw new Error("Inicia sesion como administrador para ver configuracion.")
    }

    return window.CuidadoApi.fetchJson(path, {
      token,
      fallbackError: "No se pudo cargar la configuracion.",
    })
  }

  function roleLabel(role) {
    const labels = {
      admin: "Administrador",
      administrador: "Administrador",
    }

    return labels[role] || role || "Administrador"
  }

  function formatDate(value) {
    if (!value) return "Resumen de hoy"
    const [year, month, day] = String(value).split("-")
    if (!year || !month || !day) return "Resumen de hoy"
    return `Resumen del ${day}/${month}/${year}`
  }

  async function loadAdminData() {
    try {
      const [meData, summaryData] = await Promise.all([
        fetchJson("/me"),
        fetchJson("/admin/dashboard-summary"),
      ])

      const user = meData.user || {}

      setText("settingsAdminName", user.name || "Administrador")
      setText("settingsAdminEmail", `${user.email || "Sin correo"} - ${roleLabel(user.role)}`)
      setText("settingsOlderAdultsCount", summaryData.stats?.older_adults ?? 0)
      setText("settingsPendingMedicines", summaryData.medications?.pending_today ?? 0)
      setText("settingsSummaryDate", formatDate(summaryData.date))
    } catch (error) {
      setText("settingsAdminName", "Sin sesion")
      setText("settingsAdminEmail", error.message)
      setText("settingsOlderAdultsCount", 0)
      setText("settingsPendingMedicines", 0)
      setMessage(error.message, true)
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    applySettingsToForm(loadSettings())

    document.getElementById("adminSettingsForm")?.addEventListener("submit", (event) => {
      event.preventDefault()
      saveSettings(readSettingsFromForm())
      setMessage("Preferencias guardadas correctamente.")
    })

    document.getElementById("resetAdminSettings")?.addEventListener("click", () => {
      saveSettings(defaultSettings)
      applySettingsToForm(defaultSettings)
      setMessage("Preferencias restablecidas.")
    })

    loadAdminData()
  })
})()
