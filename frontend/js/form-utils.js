(() => {
  function readValue(source, key = null) {
    const value = key === null ? source?.value : source?.get(key)
    if (typeof value !== "string") return value ?? null

    const trimmed = value.trim()
    return trimmed === "" ? null : trimmed
  }

  function readPayload(fields) {
    return Object.fromEntries(
      Object.entries(fields).map(([name, source]) => {
        if (source && Object.hasOwn(source, "formData")) {
          return [name, readValue(source.formData, source.key)]
        }

        return [name, readValue(source)]
      })
    )
  }

  function findMissing(payload, requiredFields) {
    return requiredFields.filter((field) => {
      const value = payload[field]
      return value === null || value === undefined || value === ""
    })
  }

  function setDisabled(form, disabled, selector = "input, select, textarea, button") {
    form?.querySelectorAll(selector).forEach((element) => {
      element.disabled = disabled
    })
  }

  function isApproved(value) {
    return value === true || value === 1 || value === "1" || value === "true" || value === "t"
  }

  function normalizeRole(role) {
    const roles = {
      "cuidador-profesional": "cuidador_profesional",
      "cuidador-familiar": "cuidador_familiar",
    }

    return roles[role] || role
  }

  window.CuidadoForms = Object.freeze({
    findMissing,
    isApproved,
    normalizeRole,
    readPayload,
    readValue,
    setDisabled,
  })
})()
