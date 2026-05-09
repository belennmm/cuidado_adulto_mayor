(() => {
  const api = window.FamilyCare

  function getField(id) {
    return document.getElementById(id)
  }

  function setMessage(message, type = "") {
    const messageElement = getField("settingsMessage")
    if (!messageElement) return

    messageElement.textContent = message
    messageElement.classList.remove("success", "error")
    if (type) messageElement.classList.add(type)
  }

  function fillProfile(user) {
    const roleLabel = api.getRoleLabel(user.role)

    getField("fullName").value = user.name || ""
    getField("email").value = user.email || ""
    getField("phone").value = user.phone || ""
    getField("location").value = user.location || ""
    getField("birthdate").value = user.birthdate || ""
    getField("role").value = roleLabel

    api.setText("profileName", user.name || "Cuidador familiar", "")
    api.setText("profileRole", roleLabel, "")
    api.setText("profileEmail", user.email || "Sin correo", "")
  }

  function buildPayload() {
    return {
      name: getField("fullName").value.trim(),
      email: getField("email").value.trim(),
      phone: getField("phone").value.trim() || null,
      location: getField("location").value.trim() || null,
      birthdate: getField("birthdate").value || null,
      current_password: getField("currentPassword").value,
      new_password: getField("newPassword").value,
      new_password_confirmation: getField("confirmPassword").value,
    }
  }

  function clearPasswordFields() {
    const passwordFieldIds = ["currentPassword", "newPassword", "confirmPassword"]

    passwordFieldIds.forEach((id) => {
      const field = getField(id)
      if (field) field.value = ""
    })
  }

  function bindPasswordToggles() {
    document.querySelectorAll(".toggle-password").forEach((button) => {
      button.addEventListener("click", () => {
        const target = getField(button.dataset.target)
        const icon = button.querySelector("i")
        if (!target) return

        const showPassword = target.type === "password"
        target.type = showPassword ? "text" : "password"
        icon?.classList.toggle("bx-hide", !showPassword)
        icon?.classList.toggle("bx-show", showPassword)
      })
    })
  }

  async function loadProfile() {
    try {
      const data = await api.fetchJson("/me")
      fillProfile(data.user || api.getUser())
    } catch (error) {
      fillProfile(api.getUser())
      setMessage(error.message, "error")
    }
  }

  async function saveProfile(event) {
    event.preventDefault()
    setMessage("Guardando cambios...")

    try {
      const data = await api.fetchJson("/me", {
        method: "PUT",
        body: JSON.stringify(buildPayload()),
      })

      api.saveUser(data.user)
      fillProfile(data.user)
      clearPasswordFields()
      const message = data.message || "Perfil actualizado correctamente."
      setMessage(message, "success")
      await api.showAlert(message, {
        title: "Perfil actualizado",
        variant: "info",
      })
    } catch (error) {
      setMessage(error.message, "error")
      await api.showAlert(error.message, {
        title: "No se pudo guardar",
        variant: "error",
      })
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    bindPasswordToggles()
    getField("familySettingsForm")?.addEventListener("submit", saveProfile)
    loadProfile()
  })
})()
