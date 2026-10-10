import { describe, expect, it, vi } from "vitest"
import { readFileSync, readdirSync } from "node:fs"
import { resolve } from "node:path"

const attacks = ["javascript:alert(1)", "JaVaScRiPt:alert(1)", " javaScript:alert(1)", "java\nscript:alert(1)", "data:text/html,<script>alert(1)</script>", "vbscript:msgbox(1)", "file:///etc/passwd", "https://user:pass@example.com/", "https://evil.test/", "//evil.test/", "\\\\evil.test/", "javascript%3Aalert(1)"]

describe("URLs y errores públicos", () => {
  it.each(attacks)("impide navegar a %s desde las alertas", async (payload) => {
    await import("../../js/care-notifications-view.js")
    const view = window.CareNotificationsView.create({ isSoundEnabled: () => true })
    const center = document.createElement("div")
    center.innerHTML = '<div class="care-notification-list"></div>'
    view.renderCenter({ center }, [{ url: payload, title: '<svg onload=alert(1)>', body: '<img src=x onerror=alert(1)>' }])
    const link = center.querySelector("a")
    // Encoded scheme text is a harmless relative path, never a javascript URL.
    if (payload.includes("%3A")) expect(link.href).not.toMatch(/^javascript:/i)
    else expect(link.getAttribute("href")).toBe("#")
    expect(center.querySelector("svg,img,script,[onerror],[onload]")).toBeNull()
  })

  it("permite rutas locales con parámetros codificados", () => {
    const url = window.CuidadoUrls.localUrl('./routine.html?id=%3Cscript%3E')
    expect(new URL(url).origin).toBe(window.location.origin)
    expect(new URL(url).searchParams.get("id")).toBe("<script>")
  })

  it.each(["https://evil.test/api/users", "//evil.test/api/users", "javascript:alert(1)", "/../private", "https://api.example.test/private", "https://api.example.test/api/../../private"])("no envía tokens al destino %s", async (path) => {
    window.CUIDADO_API_URL = "https://api.example.test/api"
    await import("../../js/api-config.js")
    localStorage.setItem("token", "secret-token")
    const fetch = vi.fn()
    vi.stubGlobal("fetch", fetch)
    await expect(window.CuidadoApi.fetchJson(path)).rejects.toThrow()
    expect(fetch).not.toHaveBeenCalled()
  })

  it.each(["javascript:alert(1)", "data:text/html,x", "https://user:pass@api.test/api"])("rechaza una configuración API insegura: %s", async (url) => {
    window.CUIDADO_API_URL = url
    await expect(import("../../js/api-config.js")).rejects.toThrow("URL de API no válida")
  })

  it("no sigue redirecciones de API que puedan divulgar credenciales", async () => {
    await import("../../js/api-config.js")
    const fetch = vi.fn().mockResolvedValue({ ok: true, headers: { get: () => "application/json" }, json: async () => ({}) })
    vi.stubGlobal("fetch", fetch)
    await window.CuidadoApi.fetchJson("/ping", { redirect: "follow" })
    expect(fetch.mock.calls[0][1].redirect).toBe("error")
  })

  it.each(["application/json", "text/html"])("oculta SQL, HTML y trazas de errores 500 (%s)", async (type) => {
    await import("../../js/api-config.js")
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false, status: 500, headers: { get: () => type }, json: async () => ({ message: 'SQLSTATE SELECT secret <script>alert(1)</script>', trace: ['private.php'] }), text: async () => '<h1>SQLSTATE secret</h1>' }))
    await expect(window.CuidadoApi.fetchJson("/ping")).rejects.toMatchObject({ message: "Error interno del servidor.", data: { message: "Error interno del servidor." }, errors: {} })
  })

  it("renderiza un error reflejado de validación como texto inerte", async () => {
    document.body.innerHTML = '<input id="userSearchInput"><div id="usersTableBody"></div>'
    await import("../../js/ui-utils.js")
    await import("../../js/form-utils.js")
    await import("../../js/api-config.js")
    const payload = '<img src=x onerror=alert(1)><svg onload=alert(1)>'
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false, status: 422, headers: { get: () => "application/json" }, json: async () => ({ message: payload }) }))
    await import("../../js/admin/users.js")
    await new Promise(resolve => setTimeout(resolve, 0))
    const list = document.querySelector('#usersTableBody')
    expect(list.textContent).toBe(payload)
    expect(list.querySelector('img,svg,[onerror],[onload]')).toBeNull()
  })

  it("no inicia navegación ni efectos visuales con esquemas peligrosos", async () => {
    await import("../../js/app-loading-interceptors.js")
    const enabled = vi.fn()
    const mark = vi.fn()
    const view = window.AppLoadingInterceptors.create({ isLoadingEnabled: enabled, markRouteNavigation: mark })
    view.navigate('javascript:alert(1)')
    view.navigate('//evil.test/')
    expect(enabled).not.toHaveBeenCalled()
    expect(mark).not.toHaveBeenCalled()
  })

  it("descarta destinos externos al hacer clic en notificaciones nativas", async () => {
    const notification = { close: vi.fn() }
    const Notification = vi.fn(function () { return notification })
    Notification.permission = 'granted'
    vi.stubGlobal('Notification', Notification)
    vi.spyOn(window, 'focus').mockImplementation(() => {})
    await import('../../js/care-notifications-audio.js')
    const before = window.location.href
    window.CareNotificationsAudio.notify({ title: 'Alerta', body: 'texto', url: 'javascript:alert(1)' })
    notification.onclick()
    expect(window.location.href).toBe(before)
    expect(notification.close).toHaveBeenCalled()
  })

  it("bloquea enlaces DOM peligrosos antes de la navegación del navegador", async () => {
    await import("../../js/app-loading-interceptors.js")
    const interceptors = window.AppLoadingInterceptors.create({ isLoadingEnabled: () => false })
    interceptors.installNavigationInterceptor()
    const anchor = document.createElement("a")
    anchor.href = "JaVaScRiPt:alert(1)"
    document.body.append(anchor)
    const event = new MouseEvent("click", { bubbles: true, cancelable: true })
    anchor.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
  })
})

describe("CSP desplegada y compatibilidad de estilos", () => {
  it("limita scripts, conexiones, marcos y objetos sin unsafe-inline", () => {
    const config = readFileSync(resolve("nginx.conf"), "utf8")
    expect(config).not.toContain("unsafe-inline")
    for (const directive of ["script-src 'self'", "script-src-attr 'none'", "style-src-attr 'none'", "connect-src 'self'", "object-src 'none'", "frame-src https://www.youtube.com"]) expect(config).toContain(directive)
    expect(config).not.toContain("connect-src 'self' https:")
  })

  it("mantiene las páginas libres de scripts, handlers y estilos en línea", () => {
    function pages(dir) { return readdirSync(dir, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? pages(resolve(dir, entry.name)) : entry.name.endsWith('.html') ? [resolve(dir, entry.name)] : []) }
    for (const path of [resolve('index.html'), ...pages(resolve('pages'))]) {
      const html = readFileSync(path, 'utf8')
      expect(html, path).not.toMatch(/<style\b|\sstyle\s*=|\son[a-z]+\s*=/i)
      expect(html, path).not.toMatch(/<script(?![^>]*\bsrc=)[^>]*>\s*[^<\s]/i)
      expect(html, path).toContain('js/url-utils.js')
    }
  })

  it("carga los estilos de popup como hoja externa", async () => {
    await import('../../js/app-popup-styles.js')
    window.AppPopupStyles.ensure()
    expect(document.querySelector('#adminPopupStyles').tagName).toBe('LINK')
    expect(document.querySelector('#adminPopupStyles').href).toContain('/css/app-popup.css')
    expect(document.querySelector('style')).toBeNull()
  })
})
