(() => {
  function create({ state }) {
    function showInventoryFeedback(message, type = "success") {
      const feedback = document.getElementById("inventoryFeedback")
      if (!feedback) return
      feedback.textContent = message
      feedback.className = `inventory-feedback ${type}`
      feedback.hidden = false
      window.clearTimeout(showInventoryFeedback.timeoutId)
      showInventoryFeedback.timeoutId = window.setTimeout(() => { feedback.hidden = true }, 3200)
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
      const olderAdultInput = document.getElementById("medicationOlderAdult")
      const nameInput = document.getElementById("medicationName")
      const presentationInput = document.getElementById("medicationPresentation")
      const quantityInput = document.getElementById("medicationQuantity")
      const unitInput = document.getElementById("medicationUnit")
      const minimumStockInput = document.getElementById("medicationMinimumStock")
      const expirationDateInput = document.getElementById("medicationExpirationDate")
      if (!modal || !title || !submitButton || !medicationId || !olderAdultInput || !nameInput || !presentationInput || !quantityInput || !unitInput || !minimumStockInput || !expirationDateInput) return
      olderAdultInput.disabled = mode === "edit"
      title.textContent = mode === "edit" ? "Editar medicamento" : "Nuevo medicamento"
      submitButton.textContent = mode === "edit" ? "Guardar cambios" : "Guardar"
      medicationId.value = medication?.id || ""
      olderAdultInput.value = medication?.older_adult_id || document.getElementById("inventoryOlderAdultFilter")?.value || ""
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
      if (!modal || !title || !subtitle || !button || !medicationId || !actionInput) return
      title.textContent = action === "increase" ? "Sumar stock" : "Reducir stock"
      subtitle.textContent = medication.is_consolidated
        ? `Las unidades se agregarán al stock sin asignar de ${medication.name}.`
        : `${action === "increase" ? "Agrega" : "Resta"} unidades para ${medication.name}.`
      button.textContent = action === "increase" ? "Sumar" : "Reducir"
      medicationId.value = medication.id
      actionInput.value = action
      modal.hidden = false
    }

    function inventoryItemById(id) {
      return (state.displayInventory || state.inventory).find((item) => String(item.id) === String(id)) || null
    }

    return Object.freeze({ showInventoryFeedback, closeMedicationFormModal, openMedicationFormModal, closeStockAdjustmentModal, openStockAdjustmentModal, inventoryItemById })
  }

  window.MedicationStatsDialogs = Object.freeze({ create })
})()
