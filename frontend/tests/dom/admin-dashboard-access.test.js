import { beforeEach, describe, expect, it, vi } from "vitest"
import { existsSync, readFileSync } from "node:fs"
import { dirname, resolve } from "node:path"
import { fileURLToPath } from "node:url"

const testDirectory = dirname(fileURLToPath(import.meta.url))

const dashboardPages = [
  ["admin", "auth-guard-admin.js", [
    "home-page.html", "dashboard-medicines-stats.html", "users.html", "new-user.html",
    "adultos-mayores.html", "new-adulto-mayor.html", "shifts.html", "shifts-calendar.html",
    "incidents.html", "routines.html", "mobility.html", "settings.html", "user-settings.html",
  ]],
  ["cuidador-profesional", "auth-guard-profesional.js", [
    "home-page.html", "adultos-mayores.html", "shift.html", "routines.html",
    "incidents.html", "mobility.html", "user-settings.html",
  ]],
  ["cuidador-familiar", "auth-guard-familiar.js", [
    "home-page.html", "adulto-mayor.html", "routine.html", "incidents.html", "user-settings.html",
  ]],
]

describe.each(dashboardPages)("navegación del dashboard %s", (directory, guard, destinations) => {
  it("ofrece las pantallas del rol mediante rutas reales y conserva su guard", () => {
    const dashboardPath = resolve(testDirectory, "../../pages", directory, "dashboard.html")
    const page = new DOMParser().parseFromString(readFileSync(dashboardPath, "utf8"), "text/html")
    const links = [...page.querySelectorAll('a[href]:not(.logout-trigger)')]
    const hrefs = links.map((link) => link.getAttribute("href"))

    for (const destination of destinations) {
      expect(hrefs).toContain(`./${destination}`)
    }
    for (const href of hrefs) {
      expect(href).toMatch(/^\.\/[\w-]+\.html$/)
      expect(existsSync(resolve(dirname(dashboardPath), href))).toBe(true)
    }
    expect([...page.scripts].some((script) => script.getAttribute("src")?.includes(guard))).toBe(true)
  })

  it("limita Movilidad a los dashboards administrativo y profesional", () => {
    const dashboardPath = resolve(testDirectory, "../../pages", directory, "dashboard.html")
    const page = new DOMParser().parseFromString(readFileSync(dashboardPath, "utf8"), "text/html")
    expect(Boolean(page.querySelector('a[href="./mobility.html"]'))).toBe(directory !== "cuidador-familiar")
  })
})

describe("acceso exclusivo al panel administrativo", () => {
  beforeEach(() => {
    document.documentElement.style.visibility = ""
    document.body.innerHTML = '<main id="adminDashboard">Panel administrativo</main>'
    window.navigateWithLoading = vi.fn()
  })

  it("muestra el panel cuando el usuario es administrador", async () => {
    window.AuthSession = {
      hasSession: vi.fn(() => "admin-token"),
      getUser: vi.fn(() => ({ id: 1, role: "admin" })),
      refresh: vi.fn().mockResolvedValue({ id: 1, role: "admin" }),
    }

    await import("../../js/auth-guard-admin.js")

    expect(window.AuthSession.refresh).toHaveBeenCalledOnce()
    expect(document.documentElement.style.visibility).toBe("")
    expect(window.navigateWithLoading).not.toHaveBeenCalled()
  })

  it.each([
    ["profesional", "../cuidador-profesional/home-page.html"],
    ["familiar", "../cuidador-familiar/home-page.html"],
  ])("oculta el panel y redirige al usuario %s", async (role, destination) => {
    window.AuthSession = {
      hasSession: vi.fn(() => `${role}-token`),
      getUser: vi.fn(() => ({ id: 2, role })),
      refresh: vi.fn().mockResolvedValue({ id: 2, role }),
    }

    await import("../../js/auth-guard-admin.js")

    expect(document.documentElement.style.visibility).toBe("hidden")
    expect(window.navigateWithLoading).toHaveBeenCalledWith(destination)
  })

  it("oculta el panel y redirige al login cuando no existe sesión", async () => {
    window.AuthSession = {
      hasSession: vi.fn(() => ""),
      getUser: vi.fn(() => null),
      refresh: vi.fn().mockRejectedValue(new Error("401")),
    }

    await import("../../js/auth-guard-admin.js")

    expect(document.documentElement.style.visibility).toBe("hidden")
    expect(window.navigateWithLoading).toHaveBeenCalledWith("../../index.html")
  })
})
