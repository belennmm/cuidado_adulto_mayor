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
    inventoryPage: 1,
    inventoryLoadSequence: 0,
    statisticsLoadSequence: 0,
    activeFilter: "day",
    rankingMode: "top3",
    selectedMedicineId: null,
    items: [],
    inventory: [],
    inventoryIsComplete: false,
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
    const inventorySequence = ++state.inventoryLoadSequence
    const statisticsSequence = ++state.statisticsLoadSequence
    const [statisticsResult, inventoryResult, adultsResult] = await Promise.allSettled([
      service.loadStatistics(state.activeFilter),
      service.loadInventory(state.selectedOlderAdultId, state.inventoryPage),
      service.loadOlderAdults(),
    ])

    if (adultsResult.status === "fulfilled") {
      state.olderAdults = adultsResult.value.older_adults || []
      renderOlderAdultOptions()
      for (const id of ["inventoryOlderAdultFilter", "medicationOlderAdult"]) {
        window.CuidadoUi.pagedSelect(id, "/admin/older-adults", "older_adults", adultsResult.value,
          { expectedRoles: ["admin"] }, (rows) => { state.olderAdults = rows })
      }
    }
    if (inventoryResult.status === "fulfilled" && inventorySequence === state.inventoryLoadSequence) {
      state.inventory = inventoryResult.value.inventory || []
      state.inventoryIsComplete = !inventoryResult.value.pagination && !state.inventory.some((item) => item.is_consolidated)
      renderInventory()
      window.CuidadoUi.pagination("inventoryList", inventoryResult.value.pagination, loadInventoryPage)
    }
    if (statisticsSequence !== state.statisticsLoadSequence) return false
    if (statisticsResult.status === "rejected") throw statisticsResult.reason
    state.items = Array.isArray(statisticsResult.value.items) ? statisticsResult.value.items : []
    state.leader = statisticsResult.value.leader || state.items[0]
    state.statisticsPagination = statisticsResult.value.pagination
    return true
  }

  async function loadRankingPage(page) {
    const sequence = ++state.statisticsLoadSequence
    const data = await service.loadStatistics(state.activeFilter, page)
    if (sequence !== state.statisticsLoadSequence) return
    state.items = data.items || []
    state.statisticsPagination = data.pagination
    state.leader = data.leader || state.leader
    state.selectedMedicineId = state.items[0]?.id || null
    const selected = getSelectedMedicine()
    if (selected) { renderChart(selected); renderSummary(selected) }
    renderRanking(state.selectedMedicineId)
    window.CuidadoUi.pagination("medicinesRankingList", state.rankingMode === "all" ? data.pagination : null, loadRankingPage)
  }

  async function loadInventoryPage(page = 1) {
    const sequence = ++state.inventoryLoadSequence
    try {
      const data = await service.loadInventory(state.selectedOlderAdultId, page)
      if (sequence !== state.inventoryLoadSequence) return
      state.inventoryPage = page
      state.inventory = data.inventory || []
      state.inventoryIsComplete = !data.pagination && !state.inventory.some((item) => item.is_consolidated)
      renderInventory()
      window.CuidadoUi.pagination("inventoryList", data.pagination, loadInventoryPage)
    } catch (error) {
      if (sequence !== state.inventoryLoadSequence) return
      showInventoryFeedback(error.message, "error")
    }
  }

  function renderOlderAdultOptions() {
    const filter = document.getElementById("inventoryOlderAdultFilter")
    const formSelect = document.getElementById("medicationOlderAdult")
    const currentFilter = state.selectedOlderAdultId
    const option = (value, label) => {
      const node = window.CuidadoUi.element("option", "", label)
      node.value = String(value ?? "")
      return node
    }
    const adultOptions = () => state.olderAdults.map((adult) => option(adult.id, adult.full_name))
    if (filter) {
      const previousSelected = [...filter.options].find((item) => item.value === currentFilter)?.cloneNode(true)
      filter.replaceChildren(option("", "Inventario general"), option("unassigned", "Stock sin asignar"), ...adultOptions())
      if (currentFilter && ![...filter.options].some((item) => item.value === currentFilter)) {
        filter.append(previousSelected || option(currentFilter, `Adulto #${currentFilter}`))
      }
      filter.value = currentFilter
      updateOlderAdultSelection(filter.value)
    }
    if (formSelect) formSelect.replaceChildren(option("", "Stock sin asignar"), ...adultOptions())
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
      if (!(await loadFilterItems())) return

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
      window.CuidadoUi.pagination("medicinesRankingList", state.rankingMode === "all" ? state.statisticsPagination : null, loadRankingPage)
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

    document.getElementById("rankingModeGroup")?.addEventListener("click", async (event) => {
      const button = event.target.closest(".stats-filter-button[data-ranking-mode]")
      if (!button || button.dataset.rankingMode === state.rankingMode) return

      state.rankingMode = button.dataset.rankingMode
      if (state.rankingMode === "top3" && state.statisticsPagination?.current_page > 1) {
        try { await loadRankingPage(1) }
        catch (error) { showInventoryFeedback(error.message, "error"); return }
      }
      const selectedMedicine = getSelectedMedicine()
      if (selectedMedicine) renderRanking(selectedMedicine.id)
      window.CuidadoUi.pagination("medicinesRankingList", state.rankingMode === "all" ? state.statisticsPagination : null, loadRankingPage)
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
      state.selectedOlderAdultId = event.target.value
      // Only a complete collection of individual records can be filtered immediately.
      // A scoped page or consolidated totals require the new server response.
      if (state.inventoryIsComplete) renderInventory()
      else {
        renderInventoryLoading()
        window.CuidadoUi.pagination("inventoryList", null, loadInventoryPage)
      }
      loadInventoryPage(1)
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
    const hasSession = window.CuidadoApi.hasSession(["admin"])

    if (!hasSession) {
      navigateToLogin()
      return
    }

    bindEvents()

    await renderStats()
  })
})()
