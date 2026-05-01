const logoutTriggers = document.querySelectorAll(".logout-trigger")
const logoutModal = document.getElementById("logoutModal")
const cancelLogout = document.getElementById("cancelLogout")
const confirmLogout = document.getElementById("confirmLogout")

logoutTriggers.forEach((trigger) => {
  trigger.addEventListener("click", (event) => {
    event.preventDefault()
    if (logoutModal) {
      logoutModal.classList.add("active")
    }
  })
})

if (cancelLogout && logoutModal) {
  cancelLogout.addEventListener("click", () => {
    logoutModal.classList.remove("active")
  })
}

if (logoutModal) {
  logoutModal.addEventListener("click", (event) => {
    if (event.target === logoutModal) {
      logoutModal.classList.remove("active")
    }
  })
}

if (confirmLogout) {
  confirmLogout.addEventListener("click", () => {
    localStorage.removeItem("token")
    localStorage.removeItem("user")
    if (window.navigateWithLoading) {
      window.navigateWithLoading("../../index.html")
      return
    }

    window.location.assign("../../index.html")
  })
}
