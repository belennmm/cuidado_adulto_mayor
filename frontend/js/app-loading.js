(() => {
  if (window.AppLoading) {
    return
  }

  const AUTH_SHOW_DELAY_MS = 140
  const APP_SHOW_DELAY_MS = 500
  const MIN_VISIBLE_MS = 420
  const RESULT_VISIBLE_MS = 360
  const ERROR_VISIBLE_MS = 500
  const DEFAULT_MESSAGE = "Cargando..."
  const DEFAULT_SUCCESS_MESSAGE = "Listo"
  const DEFAULT_ERROR_MESSAGE = "Ocurrió un error"

  let overlay
  let statusLabel
  let statusDetail
  let isVisible = false
  let visibleSince = 0
  let activeRequests = 0
  let showTimer = null
  let hideTimer = null
  let pendingState = "success"
  let pendingMessage = DEFAULT_SUCCESS_MESSAGE
  let routeNavigationInProgress = false
  function isInsideApp() {
    const path = window.location.pathname.toLowerCase()

    return (
      path.includes("/pages/admin/") ||
      path.includes("/pages/cuidador-familiar/") ||
      path.includes("/pages/cuidador-profesional/")
    )
  }

  function getShowDelay() {
    return isInsideApp() ? APP_SHOW_DELAY_MS : AUTH_SHOW_DELAY_MS
  }

  function isLoadingEnabled() {
    if (!window.location.pathname.toLowerCase().includes("/pages/admin/")) {
      return true
    }

    try {
      const settings = JSON.parse(localStorage.getItem("adminPanelSettings") || "{}")
      return settings.showLoading !== false
    } catch (error) {
      return true
    }
  }

  function clearTimer(timerId) {
    if (timerId) {
      clearTimeout(timerId)
    }
    return null
  }

  function ensureElements() {
    if (overlay && document.body.contains(overlay)) {
      return
    }

    overlay = document.createElement("div")
    overlay.className = "app-loading-overlay"
    overlay.setAttribute("aria-hidden", "true")
    overlay.innerHTML = `
      <div class="app-loading-card" role="status" aria-live="polite" aria-atomic="true">
        <div class="app-loading-spinner" aria-hidden="true"></div>
        <div class="app-loading-state-icon" aria-hidden="true">
          <i class="bx bx-check"></i>
          <i class="bx bx-error-circle"></i>
        </div>
        <div class="app-loading-text-group">
          <strong class="app-loading-label">${DEFAULT_MESSAGE}</strong>
          <span class="app-loading-detail">Estamos preparando la información.</span>
        </div>
      </div>
    `

    document.body.appendChild(overlay)
    statusLabel = overlay.querySelector(".app-loading-label")
    statusDetail = overlay.querySelector(".app-loading-detail")
  }

  function setOverlayState(state = "loading", message = DEFAULT_MESSAGE, detail = "") {
    ensureElements()

    overlay.dataset.state = state
    if (statusLabel) {
      statusLabel.textContent = message
    }

    if (statusDetail) {
      statusDetail.textContent = detail
      statusDetail.hidden = !detail
    }
  }

  function showOverlay({
    state = "loading",
    message = DEFAULT_MESSAGE,
    detail = "Estamos preparando la información.",
  } = {}) {
    ensureElements()
    hideTimer = clearTimer(hideTimer)
    setOverlayState(state, message, detail)

    if (!isVisible) {
      overlay.classList.add("is-visible")
      overlay.setAttribute("aria-hidden", "false")
      document.body.classList.add("app-loading-active")
      isVisible = true
      visibleSince = Date.now()
    }
  }

  function hideOverlay() {
    if (!overlay || !isVisible) {
      return
    }

    overlay.classList.remove("is-visible")
    overlay.setAttribute("aria-hidden", "true")
    document.body.classList.remove("app-loading-active")
    isVisible = false
    routeNavigationInProgress = false
  }

  function finalizeOverlay(state = "success", message = DEFAULT_SUCCESS_MESSAGE, detail = "") {
    showTimer = clearTimer(showTimer)

    if (!isVisible) {
      return
    }

    const elapsed = Date.now() - visibleSince
    const waitBeforeState = Math.max(0, MIN_VISIBLE_MS - elapsed)
    const resultDuration = state === "error" ? ERROR_VISIBLE_MS : RESULT_VISIBLE_MS

    hideTimer = clearTimer(hideTimer)
    hideTimer = setTimeout(() => {
      setOverlayState(state, message, detail)
      hideTimer = clearTimer(hideTimer)
      hideTimer = setTimeout(() => {
        hideOverlay()
      }, resultDuration)
    }, waitBeforeState)
  }

  function queueShow(options = {}) {
    if (isVisible || showTimer) {
      return
    }

    showTimer = setTimeout(() => {
      showTimer = clearTimer(showTimer)
      if (activeRequests > 0 || routeNavigationInProgress) {
        showOverlay(options)
      }
    }, options.delay ?? getShowDelay())
  }

  function startRequest(message = DEFAULT_MESSAGE) {
    if (!isLoadingEnabled()) {
      return
    }

    activeRequests += 1

    if (activeRequests === 1) {
      pendingState = "success"
      pendingMessage = DEFAULT_SUCCESS_MESSAGE
      routeNavigationInProgress = false
    }

    queueShow({
      state: "loading",
      message,
      detail: "Estamos preparando la información.",
    })
  }

  function finishRequest({ ok = true, message } = {}) {
    if (!isLoadingEnabled() && activeRequests === 0) {
      return
    }

    activeRequests = Math.max(0, activeRequests - 1)

    if (!ok) {
      pendingState = "error"
      pendingMessage = message || DEFAULT_ERROR_MESSAGE
    } else if (pendingState !== "error") {
      pendingState = "success"
      pendingMessage = message || DEFAULT_SUCCESS_MESSAGE
    }

    if (activeRequests > 0 || routeNavigationInProgress) {
      return
    }

    if (!isVisible && !showTimer) {
      return
    }

    if (!isVisible && showTimer) {
      showTimer = clearTimer(showTimer)
      return
    }

    const detail =
      pendingState === "error"
        ? "Intenta nuevamente en un momento."
        : ""

    finalizeOverlay(pendingState, pendingMessage, detail)
  }

  const { navigate, installFetchInterceptor, installNavigationInterceptor } = window.AppLoadingInterceptors.create({ isLoadingEnabled, queueShow, getShowDelay, startRequest, finishRequest, markRouteNavigation: () => { routeNavigationInProgress = true }, successMessage: DEFAULT_SUCCESS_MESSAGE, errorMessage: DEFAULT_ERROR_MESSAGE })

  window.AppLoading = {
    show(message = DEFAULT_MESSAGE, detail = "Estamos preparando la información.") {
      if (!isLoadingEnabled()) return
      showOverlay({ state: "loading", message, detail })
    },
    success(message = DEFAULT_SUCCESS_MESSAGE, detail = "") {
      finalizeOverlay("success", message, detail)
    },
    error(message = DEFAULT_ERROR_MESSAGE, detail = "Intenta nuevamente en un momento.") {
      finalizeOverlay("error", message, detail)
    },
    hide() {
      showTimer = clearTimer(showTimer)
      hideTimer = clearTimer(hideTimer)
      hideOverlay()
    },
    navigate,
  }

  window.navigateWithLoading = navigate
  installFetchInterceptor()
  installNavigationInterceptor()
})()
