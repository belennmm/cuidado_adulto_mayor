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

  function enhanceSidebarLandmarks(sidebar) {
    if (!sidebar.getAttribute("aria-label")) {
      sidebar.setAttribute("aria-label", "Navegación principal")
    }

    const navigation = sidebar.querySelector(".sidebar-nav")
    if (navigation && !navigation.getAttribute("aria-label")) {
      navigation.setAttribute("aria-label", "Menú principal")
    }
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

  function normalizePath(pathname) {
    return pathname.replace(/\/+$/, "")
  }

  function setActiveRoute(links) {
    const currentPath = normalizePath(window.location.pathname)
    const currentFile = currentPath.split("/").pop()
    let matchedLink = null

    for (const link of links) {
      const href = link.getAttribute("href")
      if (!href || href === "#") continue

      try {
        const url = new URL(href, window.location.href)
        const linkFile = url.pathname.split("/").pop()

        if (normalizePath(url.pathname) === currentPath || linkFile === currentFile) {
          matchedLink = link
          break
        }
      } catch {
        // Ignore malformed links and preserve the active option from the markup.
      }
    }

    if (!matchedLink) return

    links.forEach((link) => link.classList.toggle("active", link === matchedLink))
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

  function createSidebarToggle(sidebar) {
    const sidebarTop = sidebar.querySelector(".sidebar-top")
    if (!sidebarTop) return null

    if (!sidebar.id) sidebar.id = "main-sidebar"

    const button = document.createElement("button")
    button.type = "button"
    button.className = "sidebar-toggle"
    button.setAttribute("aria-controls", sidebar.id)

    const icon = document.createElement("i")
    icon.className = "bx bx-menu"
    icon.setAttribute("aria-hidden", "true")

    const label = document.createElement("span")
    label.className = "sr-only"

    button.append(icon, label)
    sidebarTop.appendChild(button)
    return button
  }

  function updateSidebarToggle(sidebar, isExpanded) {
    const button = sidebar.querySelector(".sidebar-toggle")
    if (!button) return

    const label = isExpanded
      ? "Contraer menú de navegación"
      : "Expandir menú de navegación"

    button.setAttribute("aria-expanded", String(isExpanded))
    button.setAttribute("aria-label", label)
    button.querySelector(".sr-only").textContent = label

    const icon = button.querySelector("i")
    icon.className = isExpanded ? "bx bx-chevron-left" : "bx bx-menu"
  }

  function setSidebarState(sidebar, state, source) {
    const isExpanded = state === "expanded"
    const previousState = sidebar.dataset.state

    sidebar.dataset.state = state
    sidebar.classList.toggle("is-expanded", isExpanded)
    sidebar.classList.toggle("is-collapsed", !isExpanded)
    updateSidebarToggle(sidebar, isExpanded)

    if (previousState === state) return

    sidebar.dispatchEvent(
      new CustomEvent("sidebar:statechange", {
        bubbles: true,
        detail: { state, source },
      }),
    )
  }

  function initializeSidebarHoverEvents() {
    const sidebar = document.querySelector("aside.sidebar")
    if (!sidebar || sidebar.dataset.hoverEventsInitialized === "true") return

    sidebar.dataset.hoverEventsInitialized = "true"
    const toggleButton = createSidebarToggle(sidebar)
    let manuallyExpanded = false
    setSidebarState(sidebar, "collapsed", "initialization")

    sidebar.addEventListener("sidebar:expand-request", (event) => {
      if (event.detail?.source === "manual") manuallyExpanded = true
      setSidebarState(sidebar, "expanded", event.detail?.source || "unknown")
    })

    sidebar.addEventListener("sidebar:collapse-request", (event) => {
      if (event.detail?.source === "manual") manuallyExpanded = false
      setSidebarState(sidebar, "collapsed", event.detail?.source || "unknown")
    })

    toggleButton?.addEventListener("click", () => {
      const shouldExpand = sidebar.dataset.state !== "expanded"
      sidebar.dispatchEvent(
        new CustomEvent(
          shouldExpand ? "sidebar:expand-request" : "sidebar:collapse-request",
          {
            bubbles: true,
            detail: { source: "manual" },
          },
        ),
      )
    })

    sidebar.addEventListener("mouseenter", () => {
      sidebar.dispatchEvent(
        new CustomEvent("sidebar:expand-request", {
          bubbles: true,
          detail: { source: "mouse" },
        }),
      )
    })

    sidebar.addEventListener("mouseleave", () => {
      if (manuallyExpanded) return

      sidebar.dispatchEvent(
        new CustomEvent("sidebar:collapse-request", {
          bubbles: true,
          detail: { source: "mouse" },
        }),
      )
    })

    sidebar.addEventListener("focusin", () => {
      sidebar.dispatchEvent(
        new CustomEvent("sidebar:expand-request", {
          bubbles: true,
          detail: { source: "keyboard" },
        }),
      )
    })

    sidebar.addEventListener("focusout", (event) => {
      if (manuallyExpanded || sidebar.contains(event.relatedTarget)) return

      sidebar.dispatchEvent(
        new CustomEvent("sidebar:collapse-request", {
          bubbles: true,
          detail: { source: "keyboard" },
        }),
      )
    })

    sidebar.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        manuallyExpanded = false
        sidebar.dispatchEvent(
          new CustomEvent("sidebar:collapse-request", {
            bubbles: true,
            detail: { source: "keyboard" },
          }),
        )
        return
      }

      if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) return

      const currentLink = event.target.closest?.("a.nav-icon")
      if (!currentLink) return

      const links = Array.from(sidebar.querySelectorAll("a.nav-icon[href]"))
      const currentIndex = links.indexOf(currentLink)
      if (currentIndex === -1) return

      event.preventDefault()

      if (event.key === "Home") links[0]?.focus()
      if (event.key === "End") links[links.length - 1]?.focus()
      if (event.key === "ArrowDown") links[(currentIndex + 1) % links.length]?.focus()
      if (event.key === "ArrowUp") {
        links[(currentIndex - 1 + links.length) % links.length]?.focus()
      }
    })
  }

  document.addEventListener("DOMContentLoaded", () => {
    const links = document.querySelectorAll("aside.sidebar a.nav-icon[href]")

    links.forEach((link) => {
      const label = getLabel(link)
      ensureLabel(link, label)
      ensureSrOnlyText(link, label)
      link.querySelectorAll("i, svg").forEach((icon) => {
        icon.setAttribute("aria-hidden", "true")
      })
    })

    const sidebar = document.querySelector("aside.sidebar")
    if (sidebar) enhanceSidebarLandmarks(sidebar)
    enhanceLogoutTriggers()
    setActiveRoute(links)
    setAriaCurrentActiveLinks()
    initializeSidebarHoverEvents()
  })
})()

