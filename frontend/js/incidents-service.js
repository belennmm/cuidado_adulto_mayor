(() => {
  const INCIDENT_ROLES = Object.freeze(["admin", "profesional", "cuidador_profesional"])
  const PROFESSIONAL_ROLES = Object.freeze(["profesional", "cuidador_profesional"])

  function create({ api = window.CuidadoApi, authSession = window.AuthSession, location = window.location } = {}) {
    function normalizeRole(role) {
      return String(role || "").trim().toLowerCase()
    }

    function isAdminContext() {
      const path = String(location?.pathname || "").toLowerCase()
      const role = normalizeRole(authSession?.getUser(INCIDENT_ROLES)?.role)
      return path.includes("/pages/admin/") || role === "admin" || role === "administrador"
    }

    function getContextRoles() {
      return isAdminContext() ? ["admin"] : [...PROFESSIONAL_ROLES]
    }

    function hasSession() {
      return Boolean(api.getToken(INCIDENT_ROLES))
    }

    async function loadOlderAdults() {
      const endpoint = isAdminContext() ? "/admin/older-adults" : "/professional/older-adults"
      const data = await api.fetchJson(endpoint, {
        expectedRoles: getContextRoles(),
        fallbackError: "No se pudieron cargar los adultos mayores.",
      })
      return Array.isArray(data.older_adults) ? data.older_adults : []
    }

    function createIncident(payload) {
      const endpoint = isAdminContext() ? "/admin/incidents" : "/professional/incidents"
      return api.fetchJson(endpoint, {
        method: "POST",
        body: JSON.stringify(payload),
        expectedRoles: getContextRoles(),
        fallbackError: "No se pudo registrar el incidente.",
      })
    }

    function loadIncidents(date = "") {
      const params = new URLSearchParams()
      if (date) params.set("date", date)
      const query = params.toString()
      return api.fetchJson(`/incidents${query ? `?${query}` : ""}`, {
        expectedRoles: INCIDENT_ROLES,
        fallbackError: "No se pudieron cargar los incidentes.",
      })
    }

    return Object.freeze({ createIncident, getContextRoles, hasSession, isAdminContext, loadIncidents, loadOlderAdults })
  }

  window.IncidentsService = Object.freeze({ create })
})()
