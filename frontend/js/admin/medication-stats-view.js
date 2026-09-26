(() => {
  function create({ state, FILTER_LABELS, INVENTORY_STATUS_CLASSES, escapeHtml }) {
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

    const selectedOlderAdultId = document.getElementById("inventoryOlderAdultFilter")?.value || ""
    let visibleInventory
    if (!selectedOlderAdultId) {
      const groups = new Map()
      state.inventory.forEach((item) => {
        const key = `${item.medication_id}:${item.unit || "unidades"}`
        const group = groups.get(key) || {
          ...item,
          id: `aggregate-${key}`,
          is_consolidated: true,
          quantity: 0,
          minimum_stock: 0,
          assigned_quantity: 0,
          unassigned_quantity: 0,
          unassigned_inventory_id: null,
          expiration_dates: new Set(),
          has_undated_stock: false,
          statuses: new Set(),
        }
        const quantity = Number(item.quantity) || 0
        group.quantity += quantity
        group.minimum_stock += Number(item.minimum_stock) || 0
        group.statuses.add(item.status)
        if (item.expiration_date) group.expiration_dates.add(item.expiration_date)
        else group.has_undated_stock = true
        if (item.older_adult_id == null) {
          group.unassigned_quantity += quantity
          group.unassigned_inventory_id ||= item.id
        } else {
          group.assigned_quantity += quantity
        }
        groups.set(key, group)
      })
      visibleInventory = [...groups.values()].map((group) => {
        const expirationDates = [...group.expiration_dates]
        const sameStatus = group.statuses.size === 1
        group.status = sameStatus ? [...group.statuses][0] : "mixed"
        group.status_label = sameStatus
          ? state.inventory.find((item) => item.medication_id === group.medication_id && item.unit === group.unit)?.status_label || "Disponible"
          : "Estados variados"
        group.expiration_summary = expirationDates.length > 1
          ? "Vencimientos variados"
          : expirationDates.length === 1 && group.has_undated_stock
            ? `Algunos sin fecha · ${formatDate(expirationDates[0])}`
            : expirationDates.length === 1
              ? formatDate(expirationDates[0])
              : "Sin fecha registrada"
        return group
      })
    } else if (selectedOlderAdultId === "unassigned") {
      visibleInventory = state.inventory.filter((item) => item.older_adult_id == null)
    } else {
      visibleInventory = state.inventory.filter((item) => String(item.older_adult_id) === selectedOlderAdultId)
    }
    state.displayInventory = visibleInventory

    if (!visibleInventory.length) {
      inventoryList.innerHTML = `
        <div class="inventory-empty">
          ${selectedOlderAdultId === "unassigned" ? "No hay stock sin asignar." : "No hay medicamentos registrados para esta selección."}
        </div>
      `
      return
    }

    inventoryList.innerHTML = visibleInventory
      .map((item) => `
        <article class="inventory-item">
          <div class="inventory-item-main">
            <div class="inventory-item-heading">
              <div>
                <strong>${escapeHtml(item.name)}</strong>
                <div class="inventory-item-subtitle">
                  ${escapeHtml(item.is_consolidated ? "Inventario general consolidado" : (item.older_adult_name || "Stock sin asignar"))}${item.is_consolidated ? "" : ` &middot; ${escapeHtml(item.presentation || "Sin presentación")}`}
                </div>
              </div>
              <span class="inventory-status-badge ${item.is_consolidated && item.status === "mixed" ? "inventory-status-mixed" : statusClass(item.status)}">${escapeHtml(item.status_label)}</span>
            </div>

            <div class="inventory-item-metrics">
              <div class="inventory-metric">
                <span class="inventory-metric-label">Cantidad</span>
                <span class="inventory-metric-value">${escapeHtml(item.quantity)} ${escapeHtml(item.unit)}</span>
              </div>
              <div class="inventory-metric">
                <span class="inventory-metric-label">Stock mínimo</span>
                <span class="inventory-metric-value">${escapeHtml(item.minimum_stock)} ${escapeHtml(item.unit)}</span>
              </div>
              <div class="inventory-metric">
                <span class="inventory-metric-label">Vencimiento</span>
                <span class="inventory-metric-value">${escapeHtml(item.is_consolidated ? item.expiration_summary : formatDate(item.expiration_date))}</span>
              </div>
              <div class="inventory-metric">
                <span class="inventory-metric-label">${item.is_consolidated ? "Distribución" : "Inventario de"}</span>
                <span class="inventory-metric-value">${item.is_consolidated
                  ? `${escapeHtml(item.assigned_quantity)} asignadas · ${escapeHtml(item.unassigned_quantity)} sin asignar`
                  : escapeHtml(item.older_adult_name || "Stock sin asignar")}</span>
              </div>
            </div>
          </div>

          <div class="inventory-item-actions">
            ${item.is_consolidated ? `
              <button type="button" class="inventory-inline-button" data-action="increase" data-id="${escapeHtml(item.id)}">Sumar stock</button>
            ` : `
              <button type="button" class="inventory-inline-button" data-action="edit" data-id="${escapeHtml(item.id)}">Editar</button>
              <button type="button" class="inventory-inline-button" data-action="increase" data-id="${escapeHtml(item.id)}">Sumar stock</button>
              <button type="button" class="inventory-inline-button" data-action="decrease" data-id="${escapeHtml(item.id)}">Reducir stock</button>
              <button type="button" class="inventory-inline-button danger danger-soft-button" data-action="delete" data-id="${escapeHtml(item.id)}">Eliminar</button>
            `}
          </div>
        </article>
      `)
      .join("")
  }

  function updateOlderAdultSelection(selectedOlderAdultId) {
    state.selectedOlderAdultId = selectedOlderAdultId
    const selectedAdult = state.olderAdults.find((adult) => String(adult.id) === String(selectedOlderAdultId))
    const selectionStatus = document.getElementById("inventoryAdultSelectionStatus")

    if (selectionStatus) {
      selectionStatus.textContent = selectedOlderAdultId === "unassigned"
        ? "Mostrando únicamente el stock sin asignar."
        : selectedAdult
          ? `Mostrando medicamentos de ${selectedAdult.full_name}.`
          : "Mostrando el inventario general consolidado."
    }

    const url = new URL(window.location.href)
    if (selectedOlderAdultId === "unassigned") {
      url.searchParams.set("inventory_scope", "unassigned")
      url.searchParams.delete("older_adult_id")
    } else if (selectedAdult) {
      url.searchParams.delete("inventory_scope")
      url.searchParams.set("older_adult_id", String(selectedAdult.id))
    } else {
      url.searchParams.delete("inventory_scope")
      url.searchParams.delete("older_adult_id")
    }
    window.history.replaceState(window.history.state, "", url)
  }





    return Object.freeze({
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
    })
  }

  window.MedicationStatsView = Object.freeze({ create })
})()

