import { afterEach, beforeEach, vi } from "vitest"

beforeEach(() => {
  vi.spyOn(console, "error").mockImplementation(() => {})
})

afterEach(() => {
  document.body.innerHTML = ""
  document.head.innerHTML = ""
  localStorage.clear()
  sessionStorage.clear()
  vi.unstubAllGlobals()
  vi.resetModules()
  delete window.AuthSession
  delete window.CuidadoApi
  delete window.CuidadoConfig
  delete window.CuidadoUi
  delete window.CuidadoForms
  delete window.OlderAdultForm
  delete window.ProfessionalRoutineForm
  delete window.ProfessionalRoutinesView
  delete window.ProfessionalRoutinesService
  delete window.ProfessionalRoutinesEvents
  delete window.MedicationStatsService
  delete window.MedicationStatsView
  delete window.MedicationStatsDialogs
  delete window.CareNotificationsCore
  delete window.CareNotificationsAudio
  delete window.CareNotificationsView
  delete window.ShiftsCalendarDates
  delete window.ShiftsCalendarView
  delete window.AdminShiftsView
  delete window.AdminRoutinesView
  delete window.IncidentsView
  delete window.AppLoadingInterceptors
  delete window.ProfessionalRoutinesActions
  delete window.AdminUserForm
  delete window.CUIDADO_API_URL
  delete window.navigateWithLoading
  delete window.AppPopup
  delete window.AppPopupStyles
  delete window.showAppAlert
  delete window.showAppConfirm
  delete window.showAppSuccess
  delete window.showAdminAlert
  delete window.showAdminConfirm
})
