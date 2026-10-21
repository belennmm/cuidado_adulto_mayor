(() => {
  // Values become text nodes; this helper never interprets data as markup or attributes.
  function element(tag, className = "", text = null, children = []) {
    const node = document.createElement(tag)
    node.className = className
    if (text !== null) node.textContent = String(text ?? "")
    for (const child of children.flat(Infinity)) {
      if (child !== null && child !== undefined) node.append(child instanceof Node ? child : document.createTextNode(String(child)))
    }
    return node
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;")
  }

  function normalizeText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim()
  }

  function parseDateOnly(value) {
    if (!value) return null

    const [year, month, day] = String(value).split("-")
    if (!year || !month || !day) return null

    const date = new Date(Number(year), Number(month) - 1, Number(day))
    return Number.isNaN(date.getTime()) ? null : date
  }

  function formatLongDate(value, fallback = "Hoy") {
    const date = parseDateOnly(value)
    if (!date) return fallback

    return new Intl.DateTimeFormat("es-GT", {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    }).format(date)
  }

  function formatShortDate(value, fallback = "Sin fecha") {
    const date = parseDateOnly(value)
    if (!date) return fallback

    return new Intl.DateTimeFormat("es-GT", {
      day: "numeric",
      month: "short",
      year: "numeric",
    }).format(date)
  }

  function formatNumericDate(value, fallback = "Sin fecha") {
    const date = parseDateOnly(value)
    if (!date) return fallback

    const day = String(date.getDate()).padStart(2, "0")
    const month = String(date.getMonth() + 1).padStart(2, "0")
    return `${day}/${month}/${date.getFullYear()}`
  }

  function formatTime(value, fallback = "Sin hora") {
    return value ? String(value).slice(0, 5) : fallback
  }

  function getRoleLabel(role, fallback = "Sin rol") {
    const labels = {
      admin: "Administrador",
      administrador: "Administrador",
      profesional: "Cuidador Profesional",
      cuidador_profesional: "Cuidador Profesional",
      familiar: "Cuidador Familiar",
      cuidador_familiar: "Cuidador Familiar",
    }

    return labels[normalizeText(role)] || role || fallback
  }

  function setText(id, value, fallback = "") {
    const element = document.getElementById(id)
    if (!element) return null

    element.textContent = value ?? fallback
    return element
  }

  function setMessage(target, message, options = {}) {
    const element = typeof target === "string"
      ? document.getElementById(target)
      : target
    if (!element) return null

    const {
      type = "",
      errorClass = "is-error",
      successClass = null,
      successWhenMessage = false,
    } = options
    const hasMessage = Boolean(message)
    const isError = type === "error"
    const isSuccess = type === "success" || (successWhenMessage && hasMessage && !isError)

    element.textContent = message || ""
    if (errorClass) element.classList.toggle(errorClass, isError)
    if (successClass) element.classList.toggle(successClass, isSuccess)
    return element
  }

  function cell(label, text = null, children = [], className = "") {
    const node = element("div", className, text, children)
    node.dataset.label = label
    return node
  }

  function renderCollection(target, items, renderItem, emptyMessage, emptyClass = "empty-state") {
    if (typeof target === "string") target = document.getElementById(target)
    if (!target) return false
    const rows = Array.isArray(items) ? items : []
    target.replaceChildren(...(rows.length
      ? rows.map(renderItem)
      : [element("div", emptyClass, emptyMessage)]))
    return rows.length > 0
  }

  function navigate(url) {
    if (typeof window.navigateWithLoading === "function") window.navigateWithLoading(url)
    else window.location.assign(url)
  }

  async function notify(message, options = {}) {
    const show = window.showAppAlert || window.showAdminAlert
    if (typeof show === "function") return show(message, options)
    console.warn(message)
  }

  async function confirm(message, options = {}) {
    const show = window.showAppConfirm || window.showAdminConfirm
    if (typeof show === "function") return show(message, options)
    console.warn(message)
    return false
  }

  function pagePath(path, page = 1, filters = {}) {
    const [base, query = ""] = path.split("?")
    const params = new URLSearchParams(query)
    if (page > 1) params.set("page", String(page))
    else params.delete("page")
    for (const [key, value] of Object.entries(filters)) {
      if (value === "" || value === null || value === undefined) params.delete(key)
      else params.set(key, String(value))
    }
    return `${base}${params.size ? `?${params}` : ""}`
  }

  const pagers = new WeakMap()
  function pagination(target, meta, loadPage) {
    if (typeof target === "string") target = document.getElementById(target)
    if (!target) return
    let nav = pagers.get(target)
    if (!meta) { nav?.remove(); pagers.delete(target); return }
    if (!nav?.isConnected) {
      nav = element("nav", "list-pagination")
      nav.setAttribute("aria-label", "Páginas del listado")
      target.after(nav)
      pagers.set(target, nav)
    }
    const status = element("span", "", `Página ${meta.current_page} de ${meta.last_page}`)
    status.setAttribute("aria-live", "polite")
    const buttons = [["Anterior", meta.current_page - 1], ["Siguiente", meta.current_page + 1]].map(([label, page]) => {
      const button = element("button", "secondary-button", label)
      button.type = "button"
      button.disabled = page < 1 || page > meta.last_page
      button.addEventListener("click", async () => {
        if (button.disabled) return
        for (const node of nav.querySelectorAll("button")) node.disabled = true
        let failure
        try { await loadPage(page) }
        catch (error) { failure = error.message }
        finally {
          if (nav.contains(button)) pagination(target, meta, loadPage)
          if (failure) nav.append(element("span", "is-error", failure))
        }
      })
      return button
    })
    nav.replaceChildren(buttons[0], status, buttons[1])
  }

  // Full collections are needed for options and routine views, never ordinary lists.
  async function allPages(path, key, data, fetchPage = (url) => window.CuidadoApi.fetchJson(url)) {
    const rows = [...(data[key] || [])]
    const meta = data.pagination
    for (let page = (meta?.current_page || 1) + 1; page <= (meta?.last_page || 1); page++) {
      const next = await fetchPage(pagePath(path, page, { per_page: meta.per_page }))
      rows.push(...(next[key] || []))
    }
    return { ...data, [key]: rows }
  }

  const selectPages = new WeakMap()
  const optionLoads = new WeakMap()
  function pagedSelect(select, path, key, data, options = {}, onData = () => {}) {
    if (typeof select === "string") select = document.getElementById(select)
    if (!select || !data?.pagination) return
    selectPages.get(select)?.error?.remove()
    const request = {}
    selectPages.set(select, request)
    let loads = optionLoads.get(data)
    if (!loads) { loads = new Map(); optionLoads.set(data, loads) }
    const loadKey = JSON.stringify([path, key, options])
    if (!loads.has(loadKey)) {
      loads.set(loadKey, allPages(path, key, data, (url) => window.CuidadoApi.fetchJson(url, options)))
    }
    return loads.get(loadKey).then((result) => {
      if (selectPages.get(select) !== request) return
      const selected = select.value
      const existingOptions = new Map([...select.options].map((item) => [item.value, item.cloneNode(true)]))
      const selectedOption = [...select.options].find((item) => item.value === selected)?.cloneNode(true)
      const staticOptions = [...select.options].filter((item) => !/^\d+$/.test(item.value)).map((item) => item.cloneNode(true))
      const rows = result[key] || []
      select.replaceChildren(...staticOptions, ...rows.map((item) => {
        if (existingOptions.has(String(item.id))) return existingOptions.get(String(item.id))
        const option = element("option", "", item.full_name || item.name || "Sin nombre")
        option.value = String(item.id)
        return option
      }))
      if (selectedOption && ![...select.options].some((item) => item.value === selected)) select.append(selectedOption)
      select.value = selected
      if (rows.length > 1 && select.parentElement) select.parentElement.hidden = false
      onData(rows)
    }).catch((error) => {
      if (selectPages.get(select) !== request) return
      // Preserve existing options and surface failures without retrying limited requests.
      request.error = element("span", "is-error", error.message || "No se pudieron cargar todas las opciones.")
      request.error.setAttribute("role", "alert")
      select.after(request.error)
    })
  }
  window.CuidadoUi = Object.freeze({
    allPages,
    cell,
    confirm,
    pagePath,
    pagination,
    pagedSelect,
    element,
    escapeHtml,
    formatLongDate,
    formatNumericDate,
    formatShortDate,
    formatTime,
    getRoleLabel,
    normalizeText,
    navigate,
    parseDateOnly,
    setMessage,
    setText,
    notify,
    renderCollection,
  })
})()
