(() => {
  const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

  const FILTER_LABELS = {
    day: "Dia",
    month: "Mes",
    year: "Ano",
  }

  const INVENTORY_STATUS_CLASSES = {
    available: "inventory-status-available",
    low_stock: "inventory-status-low-stock",
    expired: "inventory-status-expired",
    expiring_soon: "inventory-status-expiring-soon",
  }

  const state = {
    activeFilter: "day",
    selectedMedicineId: null,
    items: [],
    inventory: [],
    inventoryMode: "create",
    stockAction: "increase",
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

  function buildErrorMessage(data, fallback) {
    if (data?.message) {
      return data.message
    }

    const firstError = Object.values(data?.errors || {})[0]
    if (Array.isArray(firstError) && firstError.length) {
      return firstError[0]
    }

    return fallback
  }

  async function fetchJson(path, options = {}) {
    const token = getToken()

    if (!token) {
      throw new Error("Inicia sesion como administrador para ver esta informacion.")
    }

    const response = await fetch(`${API_URL}${path}`, {
      cache: "no-store",
      ...options,
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...(options.headers || {}),
      },
    })

    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(buildErrorMessage(data, "No se pudo completar la solicitud."))
    }

    return data
  }


  function updateFilterButtons() {
    document.querySelectorAll(".stats-filter-button").forEach((button) => {
      button.classList.toggle("active", button.dataset.filter === state.activeFilter)
    })
  }

  function getSelectedMedicine() {
    if (!state.items.length) {
      return null
    }

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

  function formatDate(value) {
    if (!value) {
      return "Sin fecha"
    }

    const date = new Date(`${value}T00:00:00`)
    if (Number.isNaN(date.getTime())) {
      return value
    }

    return new Intl.DateTimeFormat("es-GT", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    }).format(date)
  }

  function statusClass(status) {
    return INVENTORY_STATUS_CLASSES[status] || INVENTORY_STATUS_CLASSES.available
  }

  function renderInventoryLoading() {
    const inventoryList = document.getElementById("inventoryList")
    if (!inventoryList) return

    inventoryList.innerHTML = `<div class="inventory-placeholder">Cargando inventario...</div>`
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
          <div class="inventory-item-main">
            <div class="inventory-item-heading">
              <div>
                <strong>${escapeHtml(item.name)}</strong>
                <div class="inventory-item-subtitle">${escapeHtml(item.presentation || "Sin presentacion")}</div>
              </div>
              <span class="inventory-status-badge ${statusClass(item.status)}">${escapeHtml(item.status_label)}</span>
            </div>

            <div class="inventory-item-metrics">
              <div class="inventory-metric">
                <span class="inventory-metric-label">Cantidad</span>
                <span class="inventory-metric-value">${escapeHtml(item.quantity)} ${escapeHtml(item.unit)}</span>
              </div>
              <div class="inventory-metric">
                <span class="inventory-metric-label">Stock minimo</span>
                <span class="inventory-metric-value">${escapeHtml(item.minimum_stock)} ${escapeHtml(item.unit)}</span>
              </div>
              <div class="inventory-metric">
                <span class="inventory-metric-label">Vencimiento</span>
                <span class="inventory-metric-value">${escapeHtml(formatDate(item.expiration_date))}</span>
              </div>
              <div class="inventory-metric">
                <span class="inventory-metric-label">Uso actual</span>
                <span class="inventory-metric-value">${escapeHtml(item.assigned_patients)} pacientes</span>
              </div>
            </div>
          </div>

          <div class="inventory-item-actions">
            <button type="button" class="inventory-inline-button" data-action="edit" data-id="${escapeHtml(item.id)}">Editar</button>
            <button type="button" class="inventory-inline-button" data-action="increase" data-id="${escapeHtml(item.id)}">Sumar stock</button>
            <button type="button" class="inventory-inline-button" data-action="decrease" data-id="${escapeHtml(item.id)}">Reducir stock</button>
            <button type="button" class="inventory-inline-button danger" data-action="delete" data-id="${escapeHtml(item.id)}">Eliminar</button>
          </div>
        </article>
      `)
      .join("")
  }





  async function loadFilterItems() {
    const data = await fetchJson(`/admin/medication-statistics?filter=${encodeURIComponent(state.activeFilter)}`)
    state.items = data.items || []
    state.inventory = data.inventory || []
  }

  async function renderStats() {
    updateFilterButtons()

    try {
      await loadFilterItems()
      renderInventory()

      if (!state.items.length) {
        state.selectedMedicineId = null
        const statsLayout = document.getElementById("medicinesStatsLayout")
        if (statsLayout) statsLayout.hidden = true
        return
      }

      const statsLayout = document.getElementById("medicinesStatsLayout")
      if (statsLayout) statsLayout.hidden = false

      const selectedMedicine = getSelectedMedicine()
      state.selectedMedicineId = selectedMedicine.id

      renderSummary(selectedMedicine)
      renderChart(selectedMedicine)
      renderRanking(selectedMedicine.id)
    } catch (error) {
      console.error(error)
      renderInventory()
    }
  }

  function showInventoryFeedback(message, type = "success") {
    const feedback = document.getElementById("inventoryFeedback")
    if (!feedback) return

    feedback.textContent = message
    feedback.className = `inventory-feedback ${type}`
    feedback.hidden = false

    window.clearTimeout(showInventoryFeedback.timeoutId)
    showInventoryFeedback.timeoutId = window.setTimeout(() => {
      feedback.hidden = true
    }, 3200)
  }

  function closeMedicationFormModal() {
    const modal = document.getElementById("medicationFormModal")
    const form = document.getElementById("medicationForm")
    if (modal) modal.hidden = true
    if (form) form.reset()
    const medicationId = document.getElementById("medicationId")
    if (medicationId) medicationId.value = ""
  }

  function openMedicationFormModal(mode, medication = null) {
    state.inventoryMode = mode
    const modal = document.getElementById("medicationFormModal")
    const title = document.getElementById("medicationFormTitle")
    const submitButton = document.getElementById("submitMedicationFormButton")
    const medicationId = document.getElementById("medicationId")
    const nameInput = document.getElementById("medicationName")
    const presentationInput = document.getElementById("medicationPresentation")
    const quantityInput = document.getElementById("medicationQuantity")
    const unitInput = document.getElementById("medicationUnit")
    const minimumStockInput = document.getElementById("medicationMinimumStock")
    const expirationDateInput = document.getElementById("medicationExpirationDate")

    if (!modal || !title || !submitButton || !medicationId || !nameInput || !presentationInput || !quantityInput || !unitInput || !minimumStockInput || !expirationDateInput) {
      return
    }

    title.textContent = mode === "edit" ? "Editar medicamento" : "Nuevo medicamento"
    submitButton.textContent = mode === "edit" ? "Guardar cambios" : "Guardar"

    medicationId.value = medication?.id || ""
    nameInput.value = medication?.name || ""
    presentationInput.value = medication?.presentation || ""
    quantityInput.value = medication?.quantity ?? ""
    unitInput.value = medication?.unit || "tabletas"
    minimumStockInput.value = medication?.minimum_stock ?? ""
    expirationDateInput.value = medication?.expiration_date || ""

    modal.hidden = false
  }

  function closeStockAdjustmentModal() {
    const modal = document.getElementById("stockAdjustmentModal")
    const form = document.getElementById("stockAdjustmentForm")
    if (modal) modal.hidden = true
    if (form) form.reset()
  }

  function openStockAdjustmentModal(action, medication) {
    state.stockAction = action

    const modal = document.getElementById("stockAdjustmentModal")
    const title = document.getElementById("stockAdjustmentTitle")
    const subtitle = document.getElementById("stockAdjustmentSubtitle")
    const button = document.getElementById("submitStockAdjustmentButton")
    const medicationId = document.getElementById("stockMedicationId")
    const actionInput = document.getElementById("stockActionType")

    if (!modal || !title || !subtitle || !button || !medicationId || !actionInput) {
      return
    }

    title.textContent = action === "increase" ? "Sumar stock" : "Reducir stock"
    subtitle.textContent = `${action === "increase" ? "Agrega" : "Resta"} unidades para ${medication.name}.`
    button.textContent = action === "increase" ? "Sumar" : "Reducir"
    medicationId.value = medication.id
    actionInput.value = action
    modal.hidden = false
  }

  function inventoryItemById(id) {
    return state.inventory.find((item) => String(item.id) === String(id)) || null
  }

  async function handleMedicationFormSubmit(event) {
    event.preventDefault()

    const medicationId = document.getElementById("medicationId")?.value
    const payload = {
      name: document.getElementById("medicationName")?.value.trim(),
      presentation: document.getElementById("medicationPresentation")?.value.trim(),
      quantity: Number(document.getElementById("medicationQuantity")?.value || 0),
      unit: document.getElementById("medicationUnit")?.value.trim(),
      minimum_stock: Number(document.getElementById("medicationMinimumStock")?.value || 0),
      expiration_date: document.getElementById("medicationExpirationDate")?.value,
    }

    try {
      const path = medicationId
        ? `/admin/medications/inventory/${encodeURIComponent(medicationId)}`
        : "/admin/medications/inventory"
      const method = medicationId ? "PUT" : "POST"

      const data = await fetchJson(path, {
        method,
        body: JSON.stringify(payload),
      })

      closeMedicationFormModal()
      showInventoryFeedback(data.message || "Inventario actualizado correctamente.")
      await renderStats()
    } catch (error) {
      showInventoryFeedback(error.message || "No se pudo guardar el medicamento.", "error")
    }
  }

  async function handleStockAdjustmentSubmit(event) {
    event.preventDefault()

    const medicationId = document.getElementById("stockMedicationId")?.value
    const action = document.getElementById("stockActionType")?.value
    const amount = Number(document.getElementById("stockAmount")?.value || 0)

    try {
      const data = await fetchJson(`/admin/medications/inventory/${encodeURIComponent(medicationId)}/stock`, {
        method: "PATCH",
        body: JSON.stringify({
          action,
          amount,
        }),
      })

      closeStockAdjustmentModal()
      showInventoryFeedback(data.message || "Stock actualizado correctamente.")
      await renderStats()
    } catch (error) {
      showInventoryFeedback(error.message || "No se pudo ajustar el stock.", "error")
    }
  }

  async function handleInventoryAction(action, medicationId) {
    const medication = inventoryItemById(medicationId)
    if (!medication) return

    if (action === "edit") {
      openMedicationFormModal("edit", medication)
      return
    }

    if (action === "increase" || action === "decrease") {
      openStockAdjustmentModal(action, medication)
      return
    }

    if (action === "delete") {
      const confirmed = window.confirm(`¿Deseas eliminar ${medication.name}?`)
      if (!confirmed) return

      try {
        const data = await fetchJson(`/admin/medications/inventory/${encodeURIComponent(medication.id)}`, {
          method: "DELETE",
        })

        showInventoryFeedback(data.message || "Medicamento eliminado correctamente.")
        await renderStats()
      } catch (error) {
        showInventoryFeedback(error.message || "No se pudo eliminar el medicamento.", "error")
      }
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

    document.getElementById("medicinesRankingList")?.addEventListener("click", (event) => {
      const button = event.target.closest(".ranking-item[data-medicine-id]")
      if (!button) return

      state.selectedMedicineId = button.dataset.medicineId
      const selectedMedicine = getSelectedMedicine()
      if (!selectedMedicine) return

      renderSummary(selectedMedicine)
      renderChart(selectedMedicine)
      renderRanking(selectedMedicine.id)
    })

    document.getElementById("openNewMedicationButton")?.addEventListener("click", () => {
      openMedicationFormModal("create")
    })

    document.getElementById("inventoryList")?.addEventListener("click", async (event) => {
      const button = event.target.closest("[data-action][data-id]")
      if (!button) return

      await handleInventoryAction(button.dataset.action, button.dataset.id)
    })

    document.getElementById("closeMedicationFormModal")?.addEventListener("click", closeMedicationFormModal)
    document.getElementById("cancelMedicationFormButton")?.addEventListener("click", closeMedicationFormModal)
    document.getElementById("medicationFormModal")?.addEventListener("click", (event) => {
      if (event.target.id === "medicationFormModal") {
        closeMedicationFormModal()
      }
    })
    document.getElementById("medicationForm")?.addEventListener("submit", handleMedicationFormSubmit)

    document.getElementById("closeStockAdjustmentModal")?.addEventListener("click", closeStockAdjustmentModal)
    document.getElementById("cancelStockAdjustmentButton")?.addEventListener("click", closeStockAdjustmentModal)
    document.getElementById("stockAdjustmentModal")?.addEventListener("click", (event) => {
      if (event.target.id === "stockAdjustmentModal") {
        closeStockAdjustmentModal()
      }
    })
    document.getElementById("stockAdjustmentForm")?.addEventListener("submit", handleStockAdjustmentSubmit)
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


  if (dayButton) {
    dayButton.click()
    return
  }

  await renderStats()
})
})()
