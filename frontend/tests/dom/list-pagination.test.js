import { beforeEach, describe, expect, it, vi } from "vitest"

const meta = { current_page: 1, last_page: 2, per_page: 25, total: 30 }
const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe("paginación de listas sensibles", () => {
  beforeEach(async () => {
    document.body.innerHTML = '<div id="list"></div><select id="adult"><option value="">Selecciona</option><option value="1" selected>Rosa</option></select>'
    await import("../../js/ui-utils.js")
  })

  it("conserva los filtros al cambiar de página y elimina parámetros vacíos", () => {
    expect(window.CuidadoUi.pagePath("/incidents?date=2026-10-10", 2)).toBe("/incidents?date=2026-10-10&page=2")
    expect(window.CuidadoUi.pagePath("/admin/users?page=2&q=viejo", 1, { q: "" })).toBe("/admin/users")
  })

  it("no descarga páginas automáticamente y bloquea clics duplicados mientras carga", async () => {
    let resolve
    const load = vi.fn(() => new Promise((done) => { resolve = done }))
    window.CuidadoUi.pagination("list", meta, load)
    expect(load).not.toHaveBeenCalled()
    expect(document.querySelector(".list-pagination span").textContent).toBe("Página 1 de 2")
    const buttons = document.querySelectorAll(".list-pagination button")
    expect(buttons[0].disabled).toBe(true)
    buttons[1].click()
    buttons[1].click()
    expect(load).toHaveBeenCalledTimes(1)
    expect(load).toHaveBeenCalledWith(2)
    resolve()
    await flush()
    expect(document.querySelectorAll(".list-pagination button")[1].disabled).toBe(false)
  })

  it("carga las opciones sin paginador ni buscador y conserva la selección", async () => {
    const fetchJson = vi.fn().mockResolvedValue({ older_adults: [{ id: 26, full_name: 'Ana <img onerror="alert(1)">' }], pagination: { ...meta, current_page: 2 } })
    window.CuidadoApi = { fetchJson }
    const onData = vi.fn()
    await window.CuidadoUi.pagedSelect("adult", "/family/older-adults", "older_adults",
      { older_adults: [{ id: 1, full_name: "Rosa" }], pagination: meta }, {}, onData)
    expect(document.querySelector(".list-pagination")).toBeNull()
    expect(document.querySelector("input[type=search]")).toBeNull()
    expect(fetchJson).toHaveBeenCalledTimes(1)
    expect(fetchJson).toHaveBeenCalledWith("/family/older-adults?page=2&per_page=25", {})
    const select = document.getElementById("adult")
    expect(select.value).toBe("1")
    expect([...select.options].map((option) => option.value)).toEqual(["", "1", "26"])
    expect(select.querySelector("img")).toBeNull()
    expect(onData.mock.calls[0][0].map((row) => row.id)).toEqual([1, 26])
  })

  it("muestra el error sin reintentar una solicitud limitada", async () => {
    const load = vi.fn().mockRejectedValue(new Error("Demasiadas solicitudes"))
    window.CuidadoUi.pagination("list", meta, load)
    document.querySelector(".list-pagination button:last-child").click()
    await flush()
    expect(load).toHaveBeenCalledTimes(1)
    expect(document.querySelector(".list-pagination").textContent).toContain("Demasiadas solicitudes")
  })

  it("busca usuarios en el servidor en vez de filtrar solamente la primera página", async () => {
    document.body.innerHTML = '<input id="userSearchInput"><div id="usersTableBody"></div>'
    const fetchJson = vi.fn().mockResolvedValue({ users: [], pagination: meta })
    window.CuidadoApi = { fetchJson }
    window.CuidadoForms = { isApproved: Boolean }
    await import("../../js/admin/users.js")
    await flush()
    const search = document.getElementById("userSearchInput")
    search.value = "Solicitud antigua"
    search.dispatchEvent(new Event("input"))
    await new Promise((resolve) => setTimeout(resolve, 450))
    expect(fetchJson.mock.calls[1][0]).toBe("/admin/users?q=Solicitud+antigua")
  })

  it("un error de una búsqueda anterior no reemplaza los resultados actuales", async () => {
    document.body.innerHTML = '<input id="userSearchInput"><div id="usersTableBody"></div>'
    let rejectFirst
    const first = new Promise((resolve, reject) => { rejectFirst = reject })
    const fetchJson = vi.fn().mockImplementationOnce(() => first).mockResolvedValue({
      users: [{ id: 9, name: "Resultado actual", role: "familiar", is_approved: true }], pagination: meta,
    })
    window.CuidadoApi = { fetchJson }
    window.CuidadoForms = { isApproved: Boolean }
    await import("../../js/admin/users.js")
    const search = document.getElementById("userSearchInput")
    search.value = "actual"
    search.dispatchEvent(new Event("input"))
    await new Promise((resolve) => setTimeout(resolve, 450))
    rejectFirst(new Error("Error anterior"))
    await flush()
    expect(document.getElementById("usersTableBody").textContent).toContain("Resultado actual")
    expect(document.getElementById("usersTableBody").textContent).not.toContain("Error anterior")
  })
})
