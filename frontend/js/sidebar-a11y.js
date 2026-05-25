(() => {
  function ensureLabel(element, label) {
    if (!label) return

    if (!element.getAttribute("aria-label")) {
      element.setAttribute("aria-label", label)
    }

    if (!element.getAttribute("title")) {
      element.setAttribute("title", label)
    }
  }

  function getLabel(link) {
    return (
      link.getAttribute("aria-label") ||
      link.getAttribute("data-label") ||
      link.getAttribute("title") ||
      (link.textContent || "").trim() ||
      ""
    )
  }

  function hasVisibleText(link) {
    return Array.from(link.childNodes).some((node) => {
      return node.nodeType === Node.TEXT_NODE && (node.textContent || "").trim()
    })
  }

  function ensureSrOnlyText(link, label) {
    if (!label) return
    if (hasVisibleText(link)) return
    if (link.querySelector(".sr-only")) return

    const span = document.createElement("span")
    span.className = "sr-only"
    span.textContent = label
    link.appendChild(span)
  }

  function enhanceLogoutTriggers() {
    document.querySelectorAll("a.logout-trigger").forEach((trigger) => {
      const label = getLabel(trigger) || "Cerrar sesión"
      ensureLabel(trigger, label)

      if (!trigger.getAttribute("role")) {
        trigger.setAttribute("role", "button")
      }

      trigger.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return
        event.preventDefault()
        trigger.click()
      })
    })
  }

  function setAriaCurrentActiveLinks() {
    const links = document.querySelectorAll("aside.sidebar a.nav-icon[href]")
    links.forEach((link) => {
      if (link.classList.contains("active")) {
        link.setAttribute("aria-current", "page")
      } else {
        link.removeAttribute("aria-current")
      }
    })
  }

  document.addEventListener("DOMContentLoaded", () => {
    const links = document.querySelectorAll("aside.sidebar a.nav-icon[href]")

    links.forEach((link) => {
      const label = getLabel(link)
      ensureLabel(link, label)
      ensureSrOnlyText(link, label)
    })

    enhanceLogoutTriggers()
    setAriaCurrentActiveLinks()
  })
})()

