(() => {
  const scriptUrl = document.currentScript?.src || new URL("/js/app-popup-styles.js", window.location.href).href
  const stylesheet = new URL("../css/app-popup.css", scriptUrl).href
  function ensureAdminPopupStyles() {
    if (document.getElementById("adminPopupStyles")) return
    const link = document.createElement("link")
    link.id = "adminPopupStyles"
    link.rel = "stylesheet"
    link.href = stylesheet
    document.head.appendChild(link)
  }
  window.AppPopupStyles = Object.freeze({ ensure: ensureAdminPopupStyles })
})()
