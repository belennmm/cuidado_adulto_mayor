(() => {
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

  window.AppPopupStyles = Object.freeze({ ensure: ensureAdminPopupStyles })
})()

