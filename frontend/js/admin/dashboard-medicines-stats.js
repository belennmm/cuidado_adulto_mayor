(() => {
  const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

  const FILTER_LABELS = {
    day: "Dia",
    month: "Mes",
    year: "Ano",
  }

  const state = {
    activeFilter: "day",
    selectedMedicineId: null,
    items: [],
    inventory: [],
  }

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

  function navigateToLogin() {
    if (window.navigateWithLoading) {
      window.navigateWithLoading("../../index.html")
      return
    }

    window.location.assign("../../index.html")
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;")
  }

  async function fetchJson(path) {
    const token = getToken()

    if (!token) {
      throw new Error("Inicia sesion como administrador para ver estadisticas.")
    }

    const response = await fetch(`${API_URL}${path}`, {
      cache: "no-store",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
    })

    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(data.message || "No se pudo cargar la informacion solicitada.")
    }

    return data
  }

  function getStatusElements() {
    return {
      statsLayout: document.getElementById("medicinesStatsLayout"),
      emptyState: document.getElementById("statsEmptyState"),
      loadingState: document.getElementById("statsLoadingState"),
      errorState: document.getElementById("statsErrorState"),
      errorMessage: document.getElementById("statsErrorMessage"),
    }
  }

  function updateFilterButtons() {
    document.querySelectorAll(".stats-filter-button").forEach((button) => {
      button.classList.toggle("active", button.dataset.filter === state.activeFilter)
    })
  }

  function getSelectedMedicine() {
    if (!state.items.length) return null

    return state.items.find((item) => String(item.id) === String(state.selectedMedicineId)) || state.items[0]
  }

  function renderRanking(selectedId) {
    const rankingList = document.getElementById("medicinesRankingList")
    if (!rankingList) return

    rankingList.innerHTML = state.items
      .slice(0, 3)
      .map((item, index) => {
        const selectedClass = String(item.id) === String(selectedId) ? " selected" : ""

        return `
          <button type="button" class="ranking-item${selectedClass}" data-medicine-id="${escapeHtml(item.id)}">
            <div class="ranking-item-main">
              <span class="ranking-position">${index + 1}</span>
              <div class="ranking-text">
                <strong class="ranking-name">${escapeHtml(item.name)}</strong>
                <span class="ranking-meta">${escapeHtml(item.rankingNote)}</span>
              </div>
            </div>
            <span class="ranking-total">${escapeHtml(item.totalUses)}</span>
          </button>
        `
      })
      .join("")
  }

  function renderChart(medicine) {
    const chart = document.getElementById("usageChart")
    const chartTitle = document.getElementById("usageChartTitle")
    if (!chart || !chartTitle || !medicine) return

    chartTitle.textContent = `${medicine.chartTitle} de ${medicine.name}`

    const points = Array.isArray(medicine.chart) ? medicine.chart : []
    const values = points.map((point) => Number(point.value) || 0)
    const maxValue = Math.max(...values, 1)

    chart.innerHTML = points
      .map((point) => {
        const value = Number(point.value) || 0
        const height = Math.max((value / maxValue) * 100, value > 0 ? 12 : 4)

        return `
          <div class="chart-bar-card">
            <span class="chart-bar-value">${escapeHtml(value)}</span>
            <div class="chart-bar-track">
              <div class="chart-bar-fill" style="height: ${height}%"></div>
            </div>
            <span class="chart-bar-label">${escapeHtml(point.label)}</span>
          </div>
        `
      })
      .join("")
  }

  function renderSummary(selectedMedicine) {
    const leader = state.items[0]
    const topMedicineUsage = document.getElementById("topMedicineUsage")
    const topMedicineUsageCaption = document.getElementById("topMedicineUsageCaption")
    const selectedMedicineName = document.getElementById("selectedMedicineName")
    const selectedFilterLabel = document.getElementById("selectedFilterLabel")
    const selectedMedicinePatients = document.getElementById("selectedMedicinePatients")
    const selectedMedicinePatientsCaption = document.getElementById("selectedMedicinePatientsCaption")
    const selectedMedicineStreak = document.getElementById("selectedMedicineStreak")
    const selectedMedicineStreakCaption = document.getElementById("selectedMedicineStreakCaption")

    if (!leader || !selectedMedicine) return

    if (selectedMedicineName) selectedMedicineName.textContent = selectedMedicine.name
    if (selectedFilterLabel) selectedFilterLabel.textContent = FILTER_LABELS[state.activeFilter]
    if (topMedicineUsage) topMedicineUsage.textContent = leader.name
    if (topMedicineUsageCaption) topMedicineUsageCaption.textContent = leader.usageLabel
    if (selectedMedicinePatients) selectedMedicinePatients.textContent = String(selectedMedicine.patients)
    if (selectedMedicinePatientsCaption) {
      selectedMedicinePatientsCaption.textContent = `${selectedMedicine.patients} pacientes en ${FILTER_LABELS[state.activeFilter].toLowerCase()}`
    }
    if (selectedMedicineStreak) selectedMedicineStreak.textContent = String(selectedMedicine.streak)
    if (selectedMedicineStreakCaption) selectedMedicineStreakCaption.textContent = selectedMedicine.streakLabel
  }

  function renderInventory() {
    const inventoryList = document.getElementById("inventoryList")
    if (!inventoryList) return

    if (!state.inventory.length) {
      inventoryList.innerHTML = `
        <div class="inventory-empty">
          No hay medicamentos registrados en el inventario.
        </div>
      `
      return
    }

    inventoryList.innerHTML = state.inventory
      .map((item) => `
        <article class="inventory-item">
          <div>
            <strong>${escapeHtml(item.name)}</strong>
            <span>${item.is_active ? "Activo" : "Inactivo"}</span>
          </div>
          <div class="inventory-item-stats">
            <span>${escapeHtml(item.assigned_patients)} pacientes</span>
            <span>${escapeHtml(item.active_assignments)} asignaciones</span>
            <span>${escapeHtml(item.administrations_count)} administraciones</span>
          </div>
        </article>
      `)
      .join("")
  }

  function renderEmptyState() {
    const { statsLayout, emptyState, loadingState, errorState } = getStatusElements()
    if (statsLayout) statsLayout.hidden = true
    if (emptyState) emptyState.hidden = false
    if (loadingState) loadingState.hidden = true
    if (errorState) errorState.hidden = true
  }

  function renderLoadingState() {
    const { statsLayout, emptyState, loadingState, errorState } = getStatusElements()
    if (statsLayout) statsLayout.hidden = true
    if (emptyState) emptyState.hidden = true
    if (loadingState) loadingState.hidden = false
    if (errorState) errorState.hidden = true
  }

  function renderErrorState(message) {
    const { statsLayout, emptyState, loadingState, errorState, errorMessage } = getStatusElements()
    if (statsLayout) statsLayout.hidden = true
    if (emptyState) emptyState.hidden = true
    if (loadingState) loadingState.hidden = true
    if (errorState) errorState.hidden = false
    if (errorMessage) errorMessage.textContent = message
  }

  function renderReadyState() {
    const { statsLayout, emptyState, loadingState, errorState } = getStatusElements()
    if (statsLayout) statsLayout.hidden = false
    if (emptyState) emptyState.hidden = true
    if (loadingState) loadingState.hidden = true
    if (errorState) errorState.hidden = true
  }

  async function loadFilterItems() {
    const data = await fetchJson(`/admin/medication-statistics?filter=${encodeURIComponent(state.activeFilter)}`)
    state.items = data.items || []
    state.inventory = data.inventory || []
  }

  async function renderStats() {
    updateFilterButtons()
    renderLoadingState()

    try {
      await loadFilterItems()
      renderInventory()

      if (!state.items.length) {
        renderEmptyState()
        return
      }

      const selectedMedicine = getSelectedMedicine()
      state.selectedMedicineId = selectedMedicine.id

      renderReadyState()
      renderSummary(selectedMedicine)
      renderChart(selectedMedicine)
      renderRanking(selectedMedicine.id)
    } catch (error) {
      renderErrorState(error.message || "No se pudo cargar la informacion solicitada.")
    }
  }

  function bindEvents() {
    document.getElementById("statsFilterGroup")?.addEventListener("click", async (event) => {
      const button = event.target.closest(".stats-filter-button[data-filter]")
      if (!button || button.dataset.filter === state.activeFilter) return

      state.activeFilter = button.dataset.filter
      state.selectedMedicineId = null
      await renderStats()
    })

    document.getElementById("medicinesRankingList")?.addEventListener("click", async (event) => {
      const button = event.target.closest(".ranking-item[data-medicine-id]")
      if (!button) return

      state.selectedMedicineId = button.dataset.medicineId
      const selectedMedicine = getSelectedMedicine()
      if (!selectedMedicine) return

      renderSummary(selectedMedicine)
      renderChart(selectedMedicine)
      renderRanking(selectedMedicine.id)
    })
  }

  document.addEventListener("DOMContentLoaded", async () => {
    const token = getToken()
    const user = safeJsonParse(localStorage.getItem("user"))
    const role = String(user?.role || "").trim().toLowerCase()

    if (!token || role !== "admin") {
      navigateToLogin()
      return
    }

    bindEvents()
    await renderStats()
  })
})()
