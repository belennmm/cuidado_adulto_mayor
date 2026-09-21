(() => {
  const FILTER_LABELS = {
    day: "Día",
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
    olderAdults: [],
    selectedOlderAdultId: new URLSearchParams(window.location.search).get("older_adult_id") || "",
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
    const [data, adultsData] = await service.load(state.activeFilter)
    state.items = data.items || []
    state.inventory = data.inventory || []
    state.olderAdults = adultsData.older_adults || []
    renderOlderAdultOptions()
  }

  function renderOlderAdultOptions() {
    const filter = document.getElementById("inventoryOlderAdultFilter")
    const formSelect = document.getElementById("medicationOlderAdult")
    const currentFilter = state.selectedOlderAdultId
    const options = state.olderAdults
      .map((adult) => `<option value="${escapeHtml(adult.id)}">${escapeHtml(adult.full_name)}</option>`)
      .join("")

    if (filter) {
      filter.innerHTML = `<option value="">Todos los adultos mayores</option>${options}`
      filter.value = state.olderAdults.some((adult) => String(adult.id) === String(currentFilter)) ? currentFilter : ""
      updateOlderAdultSelection(filter.value)
    }

    if (formSelect) {
      formSelect.innerHTML = `<option value="">Selecciona un adulto mayor</option>${options}`
    }
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

  async function handleMedicationFormSubmit(event) {
    event.preventDefault()

    const medicationId = document.getElementById("medicationId")?.value
    const payload = {
      older_adult_id: Number(document.getElementById("medicationOlderAdult")?.value || 0),
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
      const data = await service.adjustStock(medicationId, action, amount)

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
      if (!state.olderAdults.length) {
        showInventoryFeedback("Primero registra un adulto mayor.", "error")
        return
      }
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
