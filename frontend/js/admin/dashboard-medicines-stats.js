(() => {
  const FILTER_LABELS = {
    day: "Día",
    month: "Mes",
    year: "Año",
  }

  const INVENTORY_STATUS_CLASSES = {
    available: "inventory-status-available",
    low_stock: "inventory-status-low-stock",
    expired: "inventory-status-expired",
    expiring_soon: "inventory-status-expiring-soon",
  }

  const state = {
    activeFilter: "day",
    rankingMode: "top3",
    selectedMedicineId: null,
    items: [],
    inventory: [],
    olderAdults: [],
    selectedOlderAdultId: new URLSearchParams(window.location.search).get("inventory_scope") === "unassigned"
      ? "unassigned"
      : new URLSearchParams(window.location.search).get("older_adult_id") || "",
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

  function navigateToLogin() {
    if (window.navigateWithLoading) {
      window.navigateWithLoading("../../index.html")
      return
    }

    window.location.assign("../../index.html")
  }

  const escapeHtml = window.CuidadoUi.escapeHtml

  const service = window.MedicationStatsService


  const {
    updateFilterButtons,
    getSelectedMedicine,
    renderRanking,
    renderChart,
    renderSummary,
    formatDate,
    statusClass,
    renderInventoryLoading,
    renderInventory,
    updateOlderAdultSelection,
  } = window.MedicationStatsView.create({ state, FILTER_LABELS, INVENTORY_STATUS_CLASSES, escapeHtml })

  const {
    showInventoryFeedback,
    closeMedicationFormModal,
    openMedicationFormModal,
    closeStockAdjustmentModal,
    openStockAdjustmentModal,
    inventoryItemById,
  } = window.MedicationStatsDialogs.create({ state })

  async function loadFilterItems() {
    const [statisticsResult, inventoryResult, adultsResult] = await Promise.allSettled([
      service.loadStatistics(state.activeFilter),
      service.loadInventory(),
      service.loadOlderAdults(),
    ])

    if (adultsResult.status === "fulfilled") {
      state.olderAdults = adultsResult.value.older_adults || []
      renderOlderAdultOptions()
    }
    if (inventoryResult.status === "fulfilled") {
      state.inventory = inventoryResult.value.inventory || []
      renderInventory()
    }
    if (statisticsResult.status === "rejected") throw statisticsResult.reason
    state.items = Array.isArray(statisticsResult.value.items) ? statisticsResult.value.items : []
  }

  function renderOlderAdultOptions() {
    const filter = document.getElementById("inventoryOlderAdultFilter")
    const formSelect = document.getElementById("medicationOlderAdult")
    const currentFilter = state.selectedOlderAdultId
    const options = state.olderAdults
      .map((adult) => `<option value="${escapeHtml(adult.id)}">${escapeHtml(adult.full_name)}</option>`)
      .join("")

    if (filter) {
      filter.innerHTML = `<option value="">Inventario general</option><option value="unassigned">Stock sin asignar</option>${options}`
      filter.value = currentFilter === "unassigned" || state.olderAdults.some((adult) => String(adult.id) === String(currentFilter))
        ? currentFilter
        : ""
      updateOlderAdultSelection(filter.value)
    }

    if (formSelect) {
      formSelect.innerHTML = `<option value="">Stock sin asignar</option>${options}`
    }
  }

  async function renderStats() {
    updateFilterButtons()
    const statsLayout = document.getElementById("medicinesStatsLayout")
    const statsMessage = document.getElementById("medicinesStatsMessage")
    if (statsMessage) {
      statsMessage.hidden = true
      statsMessage.textContent = ""
      statsMessage.classList.remove("stats-error-state")
      statsMessage.classList.remove("stats-empty-state")
    }

    try {
      await loadFilterItems()

      if (!state.items.length) {
        state.selectedMedicineId = null
        if (statsLayout) statsLayout.hidden = true
        if (statsMessage) {
          statsMessage.textContent = "No hay adquisiciones de medicamentos para este periodo."
          statsMessage.hidden = false
        }
        return
      }

      if (statsLayout) statsLayout.hidden = false

      const selectedMedicine = getSelectedMedicine()
      state.selectedMedicineId = selectedMedicine.id

      renderSummary(selectedMedicine)
      renderChart(selectedMedicine)
      renderRanking(selectedMedicine.id)
    } catch (error) {
      console.error(error)
      state.items = []
      state.selectedMedicineId = null
      if (statsLayout) statsLayout.hidden = true
      if (statsMessage) {
        statsMessage.textContent = error.message || "No se pudieron cargar las estadísticas de medicamentos. Intenta nuevamente."
        statsMessage.classList.remove("stats-empty-state")
        statsMessage.classList.add("stats-error-state")
        statsMessage.hidden = false
      }
    }
  }

  async function handleMedicationFormSubmit(event) {
    event.preventDefault()

    const medicationId = document.getElementById("medicationId")?.value
    const payload = {
      older_adult_id: document.getElementById("medicationOlderAdult")?.value
        ? Number(document.getElementById("medicationOlderAdult").value)
        : null,
      name: document.getElementById("medicationName")?.value.trim(),
      presentation: document.getElementById("medicationPresentation")?.value.trim(),
      quantity: Number(document.getElementById("medicationQuantity")?.value || 0),
      unit: document.getElementById("medicationUnit")?.value.trim(),
      minimum_stock: Number(document.getElementById("medicationMinimumStock")?.value || 0),
      expiration_date: document.getElementById("medicationExpirationDate")?.value,
    }

    try {
      const data = await service.saveInventory(medicationId, payload)

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
      const inventoryItem = inventoryItemById(medicationId)
      let data
      if (inventoryItem?.is_consolidated) {
        if (inventoryItem.unassigned_inventory_id) {
          data = await service.adjustStock(inventoryItem.unassigned_inventory_id, action, amount)
        } else {
          data = await service.saveInventory(null, {
            older_adult_id: null,
            name: inventoryItem.name,
            presentation: inventoryItem.presentation || "Sin presentación",
            quantity: amount,
            unit: inventoryItem.unit || "unidades",
            minimum_stock: 0,
            expiration_date: null,
          })
        }
      } else {
        data = await service.adjustStock(medicationId, action, amount)
      }

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
      const confirmed = window.showAdminConfirm
        ? await window.showAdminConfirm(`¿Deseas eliminar ${medication.name}?`, {
            title: "Eliminar medicamento",
            confirmText: "Eliminar",
          })
        : false
      if (!confirmed) return

      try {
        const data = await service.removeInventory(medication.id)

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

    document.getElementById("rankingModeGroup")?.addEventListener("click", (event) => {
      const button = event.target.closest(".stats-filter-button[data-ranking-mode]")
      if (!button || button.dataset.rankingMode === state.rankingMode) return

      state.rankingMode = button.dataset.rankingMode
      const selectedMedicine = getSelectedMedicine()
      if (selectedMedicine) renderRanking(selectedMedicine.id)
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

    document.getElementById("inventoryOlderAdultFilter")?.addEventListener("change", (event) => {
      updateOlderAdultSelection(event.target.value)
      renderInventory()
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
    const token = window.CuidadoApi.getToken(["admin"])
    const user = safeJsonParse(JSON.stringify(window.AuthSession?.getUser() || null))
    const role = String(user?.role || "").trim().toLowerCase()

    if (!token || role !== "admin") {
      navigateToLogin()
      return
    }

    bindEvents()

    await renderStats()
  })
})()
