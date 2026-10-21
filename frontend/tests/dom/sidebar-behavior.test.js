import { beforeEach, describe, expect, it, vi } from "vitest"

const roleCases = [
  ["administrador", "/pages/admin/dashboard.html", "./dashboard.html"],
  ["cuidador familiar", "/pages/cuidador-familiar/dashboard.html", "./dashboard.html"],
  ["cuidador profesional", "/pages/cuidador-profesional/dashboard.html", "./dashboard.html"],
]

function renderSidebar(activeHref) {
  document.body.innerHTML = `
    <div class="role-layout">
      <aside class="sidebar">
        <div class="sidebar-top"></div>
        <nav class="sidebar-nav">
          <a class="nav-icon" href="./home-page.html" data-label="Inicio"><i></i></a>
          <a class="nav-icon" href="${activeHref}" data-label="Dashboard"><i></i></a>
          <a class="nav-icon" href="./incidents.html" data-label="Incidentes"><i></i></a>
        </nav>
        <div class="sidebar-bottom">
          <a class="nav-icon logout-trigger" href="#" data-label="Cerrar sesión"><i></i></a>
        </div>
      </aside>
      <main>Contenido principal</main>
    </div>
  `
}

async function initializeSidebar(path, activeHref) {
  window.history.replaceState({}, "", path)
  renderSidebar(activeHref)
  await import("../../js/sidebar-a11y.js")
  document.dispatchEvent(new Event("DOMContentLoaded"))
  return document.querySelector(".sidebar")
}

describe.each(roleCases)("sidebar del rol %s", (_role, path, activeHref) => {
  beforeEach(() => {
    vi.resetModules()
  })

  it("expande y contrae con la entrada y salida del cursor", async () => {
    const sidebar = await initializeSidebar(path, activeHref)

    expect(sidebar.dataset.state).toBe("collapsed")
    sidebar.dispatchEvent(new MouseEvent("mouseenter"))
    expect(sidebar.dataset.state).toBe("expanded")
    sidebar.dispatchEvent(new MouseEvent("mouseleave"))
    expect(sidebar.dataset.state).toBe("collapsed")
  })

  it("mantiene resaltada la ruta actual en ambos estados", async () => {
    const sidebar = await initializeSidebar(path, activeHref)
    const activeLink = sidebar.querySelector(`[href="${activeHref}"]`)

    expect(activeLink.classList.contains("active")).toBe(true)
    expect(activeLink.getAttribute("aria-current")).toBe("page")

    sidebar.dispatchEvent(new MouseEvent("mouseenter"))
    sidebar.dispatchEvent(new MouseEvent("mouseleave"))

    expect(activeLink.classList.contains("active")).toBe(true)
    expect(activeLink.getAttribute("aria-current")).toBe("page")
  })

  it("permite apertura manual y evita el cierre accidental", async () => {
    const sidebar = await initializeSidebar(path, activeHref)
    const toggle = sidebar.querySelector(".sidebar-toggle")

    toggle.click()
    expect(sidebar.dataset.state).toBe("expanded")
    expect(toggle.getAttribute("aria-expanded")).toBe("true")

    sidebar.dispatchEvent(new MouseEvent("mouseleave"))
    expect(sidebar.dataset.state).toBe("expanded")

    toggle.click()
    expect(sidebar.dataset.state).toBe("collapsed")
    expect(toggle.getAttribute("aria-expanded")).toBe("false")
  })

  it("responde al foco, las flechas y Escape", async () => {
    const sidebar = await initializeSidebar(path, activeHref)
    const links = Array.from(sidebar.querySelectorAll("a.nav-icon"))

    links[0].focus()
    expect(sidebar.dataset.state).toBe("expanded")

    links[0].dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowDown", bubbles: true }))
    expect(document.activeElement).toBe(links[1])

    links[1].dispatchEvent(new KeyboardEvent("keydown", { key: "End", bubbles: true }))
    expect(document.activeElement).toBe(links[links.length - 1])

    sidebar.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }))
    expect(sidebar.dataset.state).toBe("collapsed")
  })

  it("expone nombres accesibles para navegación, controles e iconos", async () => {
    const sidebar = await initializeSidebar(path, activeHref)
    const navigation = sidebar.querySelector(".sidebar-nav")

    expect(sidebar.getAttribute("aria-label")).toBe("Navegación principal")
    expect(navigation.getAttribute("aria-label")).toBe("Menú principal")
    expect(sidebar.querySelector(".sidebar-toggle").getAttribute("aria-controls")).toBe(sidebar.id)
    expect(Array.from(sidebar.querySelectorAll("i")).every((icon) => icon.getAttribute("aria-hidden") === "true")).toBe(true)
    expect(Array.from(sidebar.querySelectorAll("a.nav-icon")).every((link) => link.getAttribute("aria-label"))).toBe(true)
  })
})
