(() => {
  if (window.AppPopup) return

  let adminPopupOverlay = null
  let adminPopupResolver = null
  let adminPopupRestoreFocus = null
  let adminPopupCleanup = null

  const POPUP_VARIANTS = {
    info: {
      title: "Aviso",
      icon: "bx bx-info-circle",
    },
    success: {
      title: "Listo",
      icon: "bx bx-check-circle",
    },
    error: {
      title: "No se pudo completar",
      icon: "bx bx-error-circle",
    },
    danger: {
      title: "Confirmar acción",
      icon: "bx bx-error-circle",
    },
    warning: {
      title: "Revisar información",
      icon: "bx bx-error-circle",
    },
  }

  const ensureAdminPopupStyles = window.AppPopupStyles.ensure

  function ensureAdminPopup() {
    ensureAdminPopupStyles()

    if (adminPopupOverlay && document.body.contains(adminPopupOverlay)) {
      return adminPopupOverlay
    }

    adminPopupOverlay = document.createElement("div")
    adminPopupOverlay.className = "admin-popup-overlay"
    adminPopupOverlay.innerHTML = `
      <div class="admin-popup-box" role="dialog" aria-modal="true" aria-labelledby="adminPopupTitle" aria-describedby="adminPopupMessage">
        <div class="admin-popup-icon" aria-hidden="true">
          <i class="bx bx-info-circle"></i>
        </div>
        <h3 class="admin-popup-title" id="adminPopupTitle"></h3>
        <p class="admin-popup-message" id="adminPopupMessage"></p>
        <div class="admin-popup-actions">
          <button type="button" class="admin-popup-cancel">Cancelar</button>
          <button type="button" class="admin-popup-confirm">Aceptar</button>
        </div>
      </div>
    `

    document.body.appendChild(adminPopupOverlay)
    return adminPopupOverlay
  }

  function normalizePopupVariant(variant) {
    return POPUP_VARIANTS[variant] ? variant : "info"
  }

  function closeAdminPopup(result) {
    if (!adminPopupOverlay) return
    adminPopupOverlay.classList.remove("active")

    if (adminPopupCleanup) {
      adminPopupCleanup()
      adminPopupCleanup = null
    }

    if (adminPopupResolver) {
      adminPopupResolver(result)
      adminPopupResolver = null
    }

    if (adminPopupRestoreFocus && typeof adminPopupRestoreFocus.focus === "function") {
      adminPopupRestoreFocus.focus()
    }
    adminPopupRestoreFocus = null
  }

  function showAdminPopup({
    title = "",
    message = "",
    confirmText = "Aceptar",
    cancelText = "Cancelar",
    showCancel = false,
    variant = "info",
  } = {}) {
    const overlay = ensureAdminPopup()
    const normalizedVariant = normalizePopupVariant(variant)
    const variantConfig = POPUP_VARIANTS[normalizedVariant]
    const dialog = overlay.querySelector(".admin-popup-box")
    const icon = overlay.querySelector(".admin-popup-icon i")
    const titleElement = overlay.querySelector(".admin-popup-title")
    const messageElement = overlay.querySelector(".admin-popup-message")
    const cancelButton = overlay.querySelector(".admin-popup-cancel")
    const confirmButton = overlay.querySelector(".admin-popup-confirm")

    adminPopupRestoreFocus = document.activeElement
    overlay.dataset.variant = normalizedVariant
    if (dialog) {
      dialog.setAttribute("role", showCancel || normalizedVariant === "error" || normalizedVariant === "danger" ? "alertdialog" : "dialog")
    }
    if (titleElement) titleElement.textContent = title || variantConfig.title
    if (messageElement) messageElement.textContent = message
    if (cancelButton) {
      cancelButton.textContent = cancelText
      cancelButton.hidden = !showCancel
    }
    if (confirmButton) confirmButton.textContent = confirmText
    if (icon) {
      icon.className = variantConfig.icon
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
      const handleKeydown = (event) => {
        if (event.key === "Escape") {
          closeAdminPopup(false)
        }
      }

      cancelButton?.addEventListener("click", handleCancel)
      confirmButton?.addEventListener("click", handleConfirm)
      overlay.addEventListener("click", handleOverlay)
      document.addEventListener("keydown", handleKeydown)
      adminPopupCleanup = () => {
        cancelButton?.removeEventListener("click", handleCancel)
        confirmButton?.removeEventListener("click", handleConfirm)
        overlay.removeEventListener("click", handleOverlay)
        document.removeEventListener("keydown", handleKeydown)
      }
      window.setTimeout(() => {
        confirmButton?.focus()
      }, 0)
    })
  }

  window.AppPopup = Object.freeze({ show: showAdminPopup })
  window.showAppAlert = (message, options = {}) => showAdminPopup({ title: options.title, message, confirmText: options.confirmText || "Aceptar", variant: options.variant || "info" })
  window.showAppConfirm = (message, options = {}) => showAdminPopup({ title: options.title, message, confirmText: options.confirmText || "Aceptar", cancelText: options.cancelText || "Cancelar", showCancel: true, variant: options.variant || "danger" })
  window.showAppSuccess = (message, options = {}) => window.showAppAlert(message, { ...options, variant: options.variant || "success" })
  window.showAdminAlert = window.showAppAlert
  window.showAdminConfirm = window.showAppConfirm
})()

