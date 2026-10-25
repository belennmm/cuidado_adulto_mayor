import { describe, expect, it } from "vitest"
import { existsSync, readFileSync, readdirSync } from "node:fs"
import { dirname, relative, resolve } from "node:path"
import { fileURLToPath } from "node:url"

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..")
const rolePages = [
  ["admin", "routines.html", "auth-guard-admin.js"],
  ["cuidador-profesional", "routines.html", "auth-guard-profesional.js"],
  ["cuidador-familiar", "routine.html", "auth-guard-familiar.js"],
]

function htmlFiles(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = resolve(directory, entry.name)
    return entry.isDirectory() ? htmlFiles(path) : entry.name.endsWith(".html") ? [path] : []
  })
}

function readPage(path) {
  return new DOMParser().parseFromString(readFileSync(path, "utf8"), "text/html")
}

const pages = [resolve(frontendRoot, "index.html"), ...htmlFiles(resolve(frontendRoot, "pages"))]

describe("enlaces locales del frontend", () => {
  it.each(pages.map((path) => [relative(frontendRoot, path), path]))("%s tiene destinos existentes", (_name, path) => {
    const page = readPage(path)
    for (const link of page.querySelectorAll("a[href]")) {
      const href = link.getAttribute("href")
      if (href === "#" && link.classList.contains("logout-trigger")) continue
      expect(href.trim()).not.toBe("")
      expect(href).not.toBe("#")
      if (/^(?:[a-z][a-z\d+.-]*:|\/\/|#)/i.test(href)) continue
      const localPath = href.split(/[?#]/)[0]
      if (!localPath) continue
      const destination = localPath.startsWith("/")
        ? resolve(frontendRoot, localPath.slice(1))
        : resolve(dirname(path), localPath)
      expect(existsSync(destination), `${relative(frontendRoot, path)} -> ${href}`).toBe(true)
    }
  })
})

describe.each(rolePages)("Rutinas e Incidentes de %s", (directory, routineFile, guard) => {
  it("tiene un acceso en el contenido hacia Incidentes del mismo rol, sin duplicarlo", () => {
    const path = resolve(frontendRoot, "pages", directory, routineFile)
    const page = readPage(path)
    const links = page.querySelectorAll('main a[href="./incidents.html"]')
    expect(links).toHaveLength(1)
    expect(links[0].textContent).toMatch(/incidentes/i)
    expect(existsSync(resolve(dirname(path), "incidents.html"))).toBe(true)
  })

  it("conserva el guard del rol en todas sus páginas y no enlaza otras áreas protegidas", () => {
    for (const path of htmlFiles(resolve(frontendRoot, "pages", directory))) {
      const page = readPage(path)
      expect([...page.scripts].some((script) => script.getAttribute("src")?.split("?")[0] === `../../js/${guard}`)).toBe(true)
      for (const link of page.querySelectorAll('a[href]')) {
        const href = link.getAttribute("href").split(/[?#]/)[0]
        if (!href || /^(?:[a-z][a-z\d+.-]*:|\/\/)/i.test(href)) continue
        const target = href.startsWith("/") ? resolve(frontendRoot, href.slice(1)) : resolve(dirname(path), href)
        const targetRole = relative(resolve(frontendRoot, "pages"), target).split(/[\\/]/)[0]
        if (rolePages.some(([role]) => role === targetRole)) expect(targetRole).toBe(directory)
      }
    }
  })
})

// Only literal routes with a known page context; templates and runtime choices
// are covered by their existing behavior tests, not guessed by this scanner.
describe("destinos sencillos de navegación JavaScript", () => {
  const contexts = [
    ["js/index.js", "index.html"],
    ["js/register.js", "pages/register.html"],
    ...["home-page", "edit-user", "edit-adulto-mayor", "new-user", "new-adulto-mayor", "shifts-calendar", "dashboard-medicines-stats"]
      .map((name) => [`js/admin/${name}.js`, `pages/admin/${name}.html`]),
    ...rolePages.flatMap(([role, , guard]) => [
      [`js/${guard}`, `pages/${role}/home-page.html`],
      ["js/logout-modal.js", `pages/${role}/home-page.html`],
    ]),
  ]
  it.each(contexts)("%s resuelve destinos desde %s", (script, page) => {
    const source = readFileSync(resolve(frontendRoot, script), "utf8")
    const literals = [...source.matchAll(/["']((?:\.\/|\.\.\/)[^"'\r\n?]*\.html)["']/g)]
    expect(literals.length).toBeGreaterThan(0)
    for (const [, href] of literals) {
      expect(existsSync(resolve(dirname(resolve(frontendRoot, page)), href)), `${script} -> ${href}`).toBe(true)
    }
  })
})
