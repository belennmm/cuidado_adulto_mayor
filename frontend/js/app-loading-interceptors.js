(() => {
  function create({ isLoadingEnabled, queueShow, getShowDelay, startRequest, finishRequest, markRouteNavigation, successMessage, errorMessage }) {
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
              message: response.ok ? successMessage : errorMessage,
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

    function navigate(url, { replace = false, message = "Abriendo página..." } = {}) {
      if (!url) return

      if (!isLoadingEnabled()) {
        if (replace) {
          window.location.replace(url)
          return
        }

        window.location.assign(url)
        return
      }

      markRouteNavigation()
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

        markRouteNavigation()
        queueShow({
          state: "loading",
          message: "Abriendo página...",
          detail: "Estamos cambiando de pantalla.",
          delay: getShowDelay(),
        })
      })
    }

    return Object.freeze({ navigate, installFetchInterceptor, installNavigationInterceptor })
  }
  window.AppLoadingInterceptors = Object.freeze({ create })
})()

