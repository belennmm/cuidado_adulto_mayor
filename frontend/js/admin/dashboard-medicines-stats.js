(() => {
  const FILTER_LABELS = {
    day: "Dia",
    month: "Mes",
    year: "Ano",
  }

  const medicinesStatsData = {
    day: [
      {
        id: "paracetamol",
        name: "Paracetamol",
        totalUses: 18,
        patients: 6,
        streak: 3,
        streakLabel: "3 dias recientes",
        usageLabel: "18 administraciones hoy",
        chartTitle: "Uso por hora del dia",
        rankingNote: "Mayor actividad durante la manana",
        chart: [
          { label: "6 AM", value: 2 },
          { label: "8 AM", value: 4 },
          { label: "10 AM", value: 3 },
          { label: "12 PM", value: 1 },
          { label: "2 PM", value: 2 },
          { label: "4 PM", value: 3 },
          { label: "6 PM", value: 2 },
          { label: "8 PM", value: 1 },
        ],
      },
      {
        id: "losartan",
        name: "Losartan",
        totalUses: 11,
        patients: 4,
        streak: 2,
        streakLabel: "2 dias recientes",
        usageLabel: "11 administraciones hoy",
        chartTitle: "Uso por hora del dia",
        rankingNote: "Concentrado en dosis matutinas",
        chart: [
          { label: "6 AM", value: 1 },
          { label: "8 AM", value: 4 },
          { label: "10 AM", value: 2 },
          { label: "12 PM", value: 0 },
          { label: "2 PM", value: 1 },
          { label: "4 PM", value: 2 },
          { label: "6 PM", value: 1 },
          { label: "8 PM", value: 0 },
        ],
      },
      {
        id: "omeprazol",
        name: "Omeprazol",
        totalUses: 9,
        patients: 3,
        streak: 2,
        streakLabel: "2 dias recientes",
        usageLabel: "9 administraciones hoy",
        chartTitle: "Uso por hora del dia",
        rankingNote: "Predomina antes del desayuno",
        chart: [
          { label: "6 AM", value: 3 },
          { label: "8 AM", value: 3 },
          { label: "10 AM", value: 1 },
          { label: "12 PM", value: 0 },
          { label: "2 PM", value: 0 },
          { label: "4 PM", value: 1 },
          { label: "6 PM", value: 1 },
          { label: "8 PM", value: 0 },
        ],
      },
    ],
    month: [
      {
        id: "metformina",
        name: "Metformina",
        totalUses: 86,
        patients: 10,
        streak: 14,
        streakLabel: "14 dias consecutivos",
        usageLabel: "86 administraciones este mes",
        chartTitle: "Uso semanal del mes",
        rankingNote: "Uso sostenido toda la semana",
        chart: [
          { label: "Sem 1", value: 17 },
          { label: "Sem 2", value: 21 },
          { label: "Sem 3", value: 19 },
          { label: "Sem 4", value: 23 },
          { label: "Sem 5", value: 6 },
        ],
      },
      {
        id: "salbutamol",
        name: "Salbutamol",
        totalUses: 71,
        patients: 7,
        streak: 11,
        streakLabel: "11 dias consecutivos",
        usageLabel: "71 administraciones este mes",
        chartTitle: "Uso semanal del mes",
        rankingNote: "Repunte en la cuarta semana",
        chart: [
          { label: "Sem 1", value: 12 },
          { label: "Sem 2", value: 14 },
          { label: "Sem 3", value: 15 },
          { label: "Sem 4", value: 20 },
          { label: "Sem 5", value: 10 },
        ],
      },
      {
        id: "ibuprofeno",
        name: "Ibuprofeno",
        totalUses: 54,
        patients: 5,
        streak: 7,
        streakLabel: "7 dias recientes",
        usageLabel: "54 administraciones este mes",
        chartTitle: "Uso semanal del mes",
        rankingNote: "Uso variable por dolor e inflamacion",
        chart: [
          { label: "Sem 1", value: 8 },
          { label: "Sem 2", value: 10 },
          { label: "Sem 3", value: 12 },
          { label: "Sem 4", value: 16 },
          { label: "Sem 5", value: 8 },
        ],
      },
    ],
    year: [
      {
        id: "losartan",
        name: "Losartan",
        totalUses: 402,
        patients: 18,
        streak: 42,
        streakLabel: "42 dias de uso reciente",
        usageLabel: "402 administraciones este ano",
        chartTitle: "Uso mensual del ano",
        rankingNote: "Consumo estable en casi todos los meses",
        chart: [
          { label: "Ene", value: 28 },
          { label: "Feb", value: 30 },
          { label: "Mar", value: 35 },
          { label: "Abr", value: 31 },
          { label: "May", value: 33 },
          { label: "Jun", value: 36 },
          { label: "Jul", value: 32 },
          { label: "Ago", value: 34 },
          { label: "Sep", value: 36 },
          { label: "Oct", value: 38 },
          { label: "Nov", value: 34 },
          { label: "Dic", value: 35 },
        ],
      },
      {
        id: "metformina",
        name: "Metformina",
        totalUses: 389,
        patients: 16,
        streak: 39,
        streakLabel: "39 dias de uso reciente",
        usageLabel: "389 administraciones este ano",
        chartTitle: "Uso mensual del ano",
        rankingNote: "Picos de uso en octubre y noviembre",
        chart: [
          { label: "Ene", value: 27 },
          { label: "Feb", value: 29 },
          { label: "Mar", value: 31 },
          { label: "Abr", value: 30 },
          { label: "May", value: 33 },
          { label: "Jun", value: 34 },
          { label: "Jul", value: 31 },
          { label: "Ago", value: 32 },
          { label: "Sep", value: 33 },
          { label: "Oct", value: 36 },
          { label: "Nov", value: 38 },
          { label: "Dic", value: 35 },
        ],
      },
      {
        id: "paracetamol",
        name: "Paracetamol",
        totalUses: 344,
        patients: 15,
        streak: 24,
        streakLabel: "24 dias de uso reciente",
        usageLabel: "344 administraciones este ano",
        chartTitle: "Uso mensual del ano",
        rankingNote: "Sube en temporadas de mayor demanda",
        chart: [
          { label: "Ene", value: 21 },
          { label: "Feb", value: 24 },
          { label: "Mar", value: 29 },
          { label: "Abr", value: 28 },
          { label: "May", value: 30 },
          { label: "Jun", value: 27 },
          { label: "Jul", value: 26 },
          { label: "Ago", value: 29 },
          { label: "Sep", value: 31 },
          { label: "Oct", value: 34 },
          { label: "Nov", value: 33 },
          { label: "Dic", value: 32 },
        ],
      },
    ],
  }

  const state = {
    activeFilter: "day",
    selectedMedicineId: null,
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

  function getCurrentItems() {
    return medicinesStatsData[state.activeFilter] || []
  }

  function getSelectedMedicine(items) {
    if (!items.length) {
      return null
    }

    const selected = items.find((item) => item.id === state.selectedMedicineId)
    return selected || items[0]
  }

  function updateFilterButtons() {
    document.querySelectorAll(".stats-filter-button").forEach((button) => {
      button.classList.toggle("active", button.dataset.filter === state.activeFilter)
    })
  }

  function renderRanking(items, selectedId) {
    const rankingList = document.getElementById("medicinesRankingList")

    if (!rankingList) {
      return
    }

    rankingList.innerHTML = items
      .slice(0, 3)
      .map((item, index) => {
        const selectedClass = item.id === selectedId ? " selected" : ""

        return `
          <button type="button" class="ranking-item${selectedClass}" data-medicine-id="${item.id}">
            <div class="ranking-item-main">
              <span class="ranking-position">${index + 1}</span>
              <div class="ranking-text">
                <strong class="ranking-name">${item.name}</strong>
                <span class="ranking-meta">${item.rankingNote}</span>
              </div>
            </div>
            <span class="ranking-total">${item.totalUses}</span>
          </button>
        `
      })
      .join("")
  }

  function renderChart(medicine) {
    const chart = document.getElementById("usageChart")
    const chartTitle = document.getElementById("usageChartTitle")

    if (!chart || !chartTitle || !medicine) {
      return
    }

    chartTitle.textContent = `${medicine.chartTitle} de ${medicine.name}`

    const values = medicine.chart.map((point) => point.value)
    const maxValue = Math.max(...values, 1)

    chart.innerHTML = medicine.chart
      .map((point) => {
        const height = Math.max((point.value / maxValue) * 100, point.value > 0 ? 12 : 4)

        return `
          <div class="chart-bar-card">
            <span class="chart-bar-value">${point.value}</span>
            <div class="chart-bar-track">
              <div class="chart-bar-fill" style="height: ${height}%"></div>
            </div>
            <span class="chart-bar-label">${point.label}</span>
          </div>
        `
      })
      .join("")
  }

  function renderSummary(items, selectedMedicine) {
    const topMedicineUsage = document.getElementById("topMedicineUsage")
    const topMedicineUsageCaption = document.getElementById("topMedicineUsageCaption")
    const selectedMedicineName = document.getElementById("selectedMedicineName")
    const selectedFilterLabel = document.getElementById("selectedFilterLabel")
    const selectedMedicinePatients = document.getElementById("selectedMedicinePatients")
    const selectedMedicinePatientsCaption = document.getElementById("selectedMedicinePatientsCaption")
    const selectedMedicineStreak = document.getElementById("selectedMedicineStreak")
    const selectedMedicineStreakCaption = document.getElementById("selectedMedicineStreakCaption")

    if (!selectedMedicine) {
      return
    }

    const leader = items[0]

    if (selectedMedicineName) {
      selectedMedicineName.textContent = selectedMedicine.name
    }

    if (selectedFilterLabel) {
      selectedFilterLabel.textContent = FILTER_LABELS[state.activeFilter]
    }

    if (topMedicineUsage) {
      topMedicineUsage.textContent = leader.name
    }

    if (topMedicineUsageCaption) {
      topMedicineUsageCaption.textContent = leader.usageLabel
    }

    if (selectedMedicinePatients) {
      selectedMedicinePatients.textContent = String(selectedMedicine.patients)
    }

    if (selectedMedicinePatientsCaption) {
      selectedMedicinePatientsCaption.textContent = `${selectedMedicine.patients} pacientes en ${FILTER_LABELS[state.activeFilter].toLowerCase()}`
    }

    if (selectedMedicineStreak) {
      selectedMedicineStreak.textContent = String(selectedMedicine.streak)
    }

    if (selectedMedicineStreakCaption) {
      selectedMedicineStreakCaption.textContent = selectedMedicine.streakLabel
    }
  }

  function renderEmptyState() {
    const statsLayout = document.getElementById("medicinesStatsLayout")
    const emptyState = document.getElementById("statsEmptyState")

    if (statsLayout) {
      statsLayout.hidden = true
    }

    if (emptyState) {
      emptyState.hidden = false
    }
  }

  function renderStats() {
    const items = getCurrentItems()
    const statsLayout = document.getElementById("medicinesStatsLayout")
    const emptyState = document.getElementById("statsEmptyState")

    updateFilterButtons()

    if (!items.length) {
      renderEmptyState()
      return
    }

    if (statsLayout) {
      statsLayout.hidden = false
    }

    if (emptyState) {
      emptyState.hidden = true
    }

    const selectedMedicine = getSelectedMedicine(items)
    state.selectedMedicineId = selectedMedicine.id

    renderSummary(items, selectedMedicine)
    renderChart(selectedMedicine)
    renderRanking(items, selectedMedicine.id)
  }

  function bindEvents() {
    document.getElementById("statsFilterGroup")?.addEventListener("click", (event) => {
      const button = event.target.closest(".stats-filter-button[data-filter]")

      if (!button || button.dataset.filter === state.activeFilter) {
        return
      }

      state.activeFilter = button.dataset.filter
      state.selectedMedicineId = null
      renderStats()
    })

    document.getElementById("medicinesRankingList")?.addEventListener("click", (event) => {
      const button = event.target.closest(".ranking-item[data-medicine-id]")

      if (!button) {
        return
      }

      state.selectedMedicineId = button.dataset.medicineId
      renderStats()
    })
  }

  document.addEventListener("DOMContentLoaded", () => {
    const token = localStorage.getItem("token")
    const user = safeJsonParse(localStorage.getItem("user"))
    const role = String(user?.role || "").trim().toLowerCase()

    if (!token || role !== "admin") {
      navigateToLogin()
      return
    }

    bindEvents()
    renderStats()
  })
})()
