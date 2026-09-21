(() => {
  function create({ form, fields, togglePassword, forms = window.CuidadoForms }) {
    function setDisabled(disabled) {
      forms.setDisabled(form, disabled)
    }

    function resetPasswordVisibility() {
      if (!fields.password) return
      fields.password.type = "password"
      const icon = togglePassword?.querySelector("i")
      if (icon) {
        icon.classList.add("bx-hide")
        icon.classList.remove("bx-show")
      }
    }

    function reset() {
      form?.reset()
      resetPasswordVisibility()
    }

    function bindPasswordToggle() {
      if (!togglePassword || !fields.password) return
      togglePassword.addEventListener("click", () => {
        const showPassword = fields.password.type === "password"
        fields.password.type = showPassword ? "text" : "password"
        const icon = togglePassword.querySelector("i")
        icon?.classList.toggle("bx-hide")
        icon?.classList.toggle("bx-show")
      })
    }

    function readPayload({ includePassword = false, includeApproval = false } = {}) {
      const controls = {
        name: fields.name,
        email: fields.email,
        role: fields.role,
        location: fields.location,
        phone: fields.phone,
        birthdate: fields.birthdate,
      }
      if (includePassword) controls.password = fields.password

      const payload = forms.readPayload(controls)
      payload.role = forms.normalizeRole(payload.role)

      if (!includePassword) {
        const password = forms.readValue(fields.password)
        if (password) payload.password = password
      }
      if (includeApproval) payload.is_approved = fields.status?.value === "Activo"
      return payload
    }

    function fill(user) {
      if (fields.role) fields.role.value = user.role || ""
      if (fields.name) fields.name.value = user.name || ""
      if (fields.email) fields.email.value = user.email || ""
      if (fields.location) fields.location.value = user.location || ""
      if (fields.phone) fields.phone.value = user.phone || ""
      if (fields.birthdate) fields.birthdate.value = user.birthdate ? String(user.birthdate).slice(0, 10) : ""
      if (fields.password) fields.password.value = ""
      if (fields.status) {
        const isAdmin = user.role === "admin"
        fields.status.value = isAdmin || forms.isApproved(user.is_approved) ? "Activo" : "Pendiente"
        fields.status.disabled = isAdmin
      }
    }

    function bindAdminStatus() {
      if (!fields.role || !fields.status) return
      fields.role.addEventListener("change", () => {
        const isAdmin = fields.role.value === "admin"
        fields.status.disabled = isAdmin
        if (isAdmin) fields.status.value = "Activo"
      })
    }

    return Object.freeze({ setDisabled, reset, bindPasswordToggle, readPayload, fill, bindAdminStatus })
  }

  window.AdminUserForm = Object.freeze({ create })
})()
