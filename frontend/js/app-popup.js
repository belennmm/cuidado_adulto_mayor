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
        border-radius: 12px;
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

      .admin-popup-overlay[data-variant="success"] .admin-popup-icon {
        background: #e8f8ef;
        color: #1e9d61;
      }

      .admin-popup-overlay[data-variant="warning"] .admin-popup-icon {
        background: #fff6e3;
        color: #b7791f;
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
        border-radius: 8px;
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

      .admin-popup-overlay[data-variant="success"] .admin-popup-confirm {
        background: #1e9d61;
      }

      .admin-popup-overlay[data-variant="warning"] .admin-popup-confirm {
        background: #b7791f;
      }

      .admin-popup-cancel:focus-visible,
      .admin-popup-confirm:focus-visible {
        outline: 3px solid rgba(29, 115, 243, 0.28);
        outline-offset: 2px;
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

