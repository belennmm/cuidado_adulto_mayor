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
  const DEFAULT_ERROR_MESSAGE = "Ocurrio un error"

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
  let adminPopupOverlay = null
  let adminPopupResolver = null

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
          <span class="app-loading-detail">Estamos preparando la informacion.</span>
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
    detail = "Estamos preparando la informacion.",
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
      detail: "Estamos preparando la informacion.",
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

  function shouldTrackFetch(input) {
    if (typeof input === "string") {
      return !input.includes("boxicons") && !input.includes("fonts.googleapis.com")
    }

    if (input instanceof Request) {
      return !input.url.includes("boxicons") && !input.url.includes("fonts.googleapis.com")
    }

    return true
  }

  function installFetchInterceptor() {
    if (typeof window.fetch !== "function" || window.fetch.__appLoadingPatched) {
      return
    }

    const originalFetch = window.fetch.bind(window)

    const wrappedFetch = async (...args) => {
      const track = shouldTrackFetch(args[0])

      if (track) {
        startRequest("Cargando...")
      }

      try {
        const response = await originalFetch(...args)

        if (track) {
          finishRequest({
            ok: response.ok,
            message: response.ok ? DEFAULT_SUCCESS_MESSAGE : DEFAULT_ERROR_MESSAGE,
          })
        }

        return response
      } catch (error) {
        if (track) {
          finishRequest({
            ok: false,
            message: "No se pudo conectar con el servidor",
          })
        }

        throw error
      }
    }

    wrappedFetch.__appLoadingPatched = true
    window.fetch = wrappedFetch
  }

  function navigate(url, { replace = false, message = "Abriendo pagina..." } = {}) {
    if (!url) return

    if (!isLoadingEnabled()) {
      if (replace) {
        window.location.replace(url)
        return
      }

      window.location.assign(url)
      return
    }

    routeNavigationInProgress = true
    queueShow({
      state: "loading",
      message,
      detail: "Estamos cambiando de pantalla.",
      delay: getShowDelay(),
    })

    window.setTimeout(() => {
      if (replace) {
        window.location.replace(url)
        return
      }

      window.location.assign(url)
    }, 20)
  }

  function installNavigationInterceptor() {
    document.addEventListener("click", (event) => {
      const anchor = event.target.closest("a[href]")
      if (!anchor) return

      const href = anchor.getAttribute("href") || ""
      if (
        !href ||
        href.startsWith("#") ||
        href.startsWith("javascript:") ||
        anchor.hasAttribute("download") ||
        anchor.target === "_blank"
      ) {
        return
      }

      let url
      try {
        url = new URL(anchor.href, window.location.href)
      } catch {
        return
      }

      if (url.origin !== window.location.origin) {
        return
      }

      if (url.pathname === window.location.pathname && url.search === window.location.search) {
        return
      }

      if (!isLoadingEnabled()) {
        return
      }

      routeNavigationInProgress = true
      queueShow({
        state: "loading",
        message: "Abriendo pagina...",
        detail: "Estamos cambiando de pantalla.",
        delay: getShowDelay(),
      })
    })
  }

  function ensureAdminPopupStyles() {
    if (document.getElementById("adminPopupStyles")) {
      return
    }

    const style = document.createElement("style")
    style.id = "adminPopupStyles"
    style.textContent = `
      .admin-popup-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
        background: rgba(15, 23, 42, 0.38);
      }

      .admin-popup-overlay.active {
        display: flex;
      }

      .admin-popup-box {
        width: 100%;
        max-width: 430px;
        padding: 24px;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.22);
        font-family: "Outfit", sans-serif;
      }

      .admin-popup-icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
        border-radius: 12px;
        background: #eef5ff;
        color: #1d73f3;
        font-size: 24px;
      }

      .admin-popup-overlay[data-variant="danger"] .admin-popup-icon,
      .admin-popup-overlay[data-variant="error"] .admin-popup-icon {
        background: #fdecec;
        color: #c94f4f;
      }

      .admin-popup-title {
        margin: 0 0 10px;
        color: #0a112f;
        font-size: 22px;
        font-weight: 700;
      }

      .admin-popup-message {
        margin: 0 0 22px;
        color: #555555;
        font-size: 15px;
        line-height: 1.5;
        white-space: pre-line;
      }

      .admin-popup-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
      }

      .admin-popup-cancel,
      .admin-popup-confirm {
        min-width: 120px;
        min-height: 42px;
        border: none;
        border-radius: 10px;
        padding: 0 14px;
        font-family: "Outfit", sans-serif;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
      }

      .admin-popup-cancel {
        background: #e4e4e7;
        color: #3f3f46;
      }

      .admin-popup-confirm {
        background: #1d73f3;
        color: #ffffff;
      }

      .admin-popup-overlay[data-variant="danger"] .admin-popup-confirm,
      .admin-popup-overlay[data-variant="error"] .admin-popup-confirm {
        background: #c94f4f;
      }

      @media screen and (max-width: 560px) {
        .admin-popup-box {
          padding: 20px 16px;
        }

        .admin-popup-actions {
          flex-direction: column;
        }

        .admin-popup-cancel,
        .admin-popup-confirm {
          width: 100%;
        }
      }
    `
    document.head.appendChild(style)
  }

  function ensureAdminPopup() {
    ensureAdminPopupStyles()

    if (adminPopupOverlay && document.body.contains(adminPopupOverlay)) {
      return adminPopupOverlay
    }

    adminPopupOverlay = document.createElement("div")
    adminPopupOverlay.className = "admin-popup-overlay"
    adminPopupOverlay.innerHTML = `
      <div class="admin-popup-box" role="dialog" aria-modal="true" aria-labelledby="adminPopupTitle">
        <div class="admin-popup-icon" aria-hidden="true">
          <i class="bx bx-info-circle"></i>
        </div>
        <h3 class="admin-popup-title" id="adminPopupTitle"></h3>
        <p class="admin-popup-message"></p>
        <div class="admin-popup-actions">
          <button type="button" class="admin-popup-cancel">Cancelar</button>
          <button type="button" class="admin-popup-confirm">Aceptar</button>
        </div>
      </div>
    `

    document.body.appendChild(adminPopupOverlay)
    return adminPopupOverlay
  }

  function closeAdminPopup(result) {
    if (!adminPopupOverlay) return
    adminPopupOverlay.classList.remove("active")

    if (adminPopupResolver) {
      adminPopupResolver(result)
      adminPopupResolver = null
    }
  }

  function showAdminPopup({
    title = "Aviso",
    message = "",
    confirmText = "Aceptar",
    cancelText = "Cancelar",
    showCancel = false,
    variant = "info",
  } = {}) {
    const overlay = ensureAdminPopup()
    const icon = overlay.querySelector(".admin-popup-icon i")
    const titleElement = overlay.querySelector(".admin-popup-title")
    const messageElement = overlay.querySelector(".admin-popup-message")
    const cancelButton = overlay.querySelector(".admin-popup-cancel")
    const confirmButton = overlay.querySelector(".admin-popup-confirm")

    overlay.dataset.variant = variant
    if (titleElement) titleElement.textContent = title
    if (messageElement) messageElement.textContent = message
    if (cancelButton) {
      cancelButton.textContent = cancelText
      cancelButton.hidden = !showCancel
    }
    if (confirmButton) confirmButton.textContent = confirmText
    if (icon) {
      icon.className = variant === "danger" || variant === "error" ? "bx bx-error-circle" : "bx bx-info-circle"
    }

    overlay.classList.add("active")

    return new Promise((resolve) => {
      adminPopupResolver = resolve

      const handleCancel = () => closeAdminPopup(false)
      const handleConfirm = () => closeAdminPopup(true)
      const handleOverlay = (event) => {
        if (event.target === overlay && showCancel) {
          closeAdminPopup(false)
        }
      }

      cancelButton?.addEventListener("click", handleCancel, { once: true })
      confirmButton?.addEventListener("click", handleConfirm, { once: true })
      overlay.addEventListener("click", handleOverlay, { once: true })
    })
  }

  window.AppLoading = {
    show(message = DEFAULT_MESSAGE, detail = "Estamos preparando la informacion.") {
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
  window.showAdminAlert = (message, options = {}) => showAdminPopup({
    title: options.title || (options.variant === "error" ? "No se pudo completar" : "Aviso"),
    message,
    confirmText: options.confirmText || "Aceptar",
    variant: options.variant || "info",
  })
  window.showAdminConfirm = (message, options = {}) => showAdminPopup({
    title: options.title || "Confirmar accion",
    message,
    confirmText: options.confirmText || "Aceptar",
    cancelText: options.cancelText || "Cancelar",
    showCancel: true,
    variant: options.variant || "danger",
  })

  installFetchInterceptor()
  installNavigationInterceptor()
})()
