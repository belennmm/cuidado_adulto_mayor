const accountSettingsForm = document.getElementById("accountSettingsForm")

const fullName = document.getElementById("fullName")
const username = document.getElementById("username")
const email = document.getElementById("email")
const phone = document.getElementById("phone")
const locationInput = document.getElementById("location")
const birthdate = document.getElementById("birthdate")
const role = document.getElementById("role")
const currentPassword = document.getElementById("currentPassword")
const newPassword = document.getElementById("newPassword")
const confirmPassword = document.getElementById("confirmPassword")
const accountSettingsMessage = document.getElementById("accountSettingsMessage")

const profileName = document.getElementById("profileName")
const profileRole = document.getElementById("profileRole")
const profileEmail = document.getElementById("profileEmail")

const togglePasswordButtons = document.querySelectorAll(".toggle-password")
const API_URL = `${window.location.protocol}//${window.location.hostname}:8080/api`

function getToken() {
  return localStorage.getItem("token")
}

function roleLabel(value) {
  const labels = {
    admin: "Administrador",
    administrador: "Administrador",
    profesional: "Cuidador Profesional",
    familiar: "Cuidador Familiar",
    cuidador_profesional: "Cuidador Profesional",
    cuidador_familiar: "Cuidador Familiar",
  }

  return labels[value] || value || "Sin rol"
}

function usernameFromEmail(value) {
  return String(value || "").split("@")[0] || ""
}

function setMessage(message, isError = false) {
  if (!accountSettingsMessage) return

  accountSettingsMessage.textContent = message || ""
  accountSettingsMessage.classList.toggle("is-error", isError)
}

function firstValidationMessage(data) {
  const errors = data?.errors || {}
  const firstField = Object.keys(errors)[0]

  if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
    return errors[firstField][0]
  }

  return data?.message || "No se pudo guardar el perfil."
}

async function fetchJson(path, options = {}) {
  const token = getToken()

  if (!token) {
    throw new Error("Inicia sesion para ver tu perfil.")
  }

  const response = await fetch(`${API_URL}${path}`, {
    cache: "no-store",
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
      ...(options.headers || {}),
    },
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(firstValidationMessage(data))
  }

  return data
}

function fillUser(user) {
  const displayRole = roleLabel(user.role)

  if (fullName) fullName.value = user.name || ""
  if (username) username.value = usernameFromEmail(user.email)
  if (email) email.value = user.email || ""
  if (phone) phone.value = user.phone || ""
  if (locationInput) locationInput.value = user.location || ""
  if (birthdate) birthdate.value = user.birthdate || ""
  if (role) role.value = displayRole

  if (profileName) profileName.textContent = user.name || "Usuario"
  if (profileRole) profileRole.textContent = displayRole
  if (profileEmail) profileEmail.textContent = user.email || "Sin correo"
}

function clearPasswordFields() {
  if (currentPassword) currentPassword.value = ""
  if (newPassword) newPassword.value = ""
  if (confirmPassword) confirmPassword.value = ""
}

async function loadProfile() {
  try {
    setMessage("")
    const data = await fetchJson("/me")
    fillUser(data.user || {})
  } catch (error) {
    setMessage(error.message, true)
  }
}

async function saveProfile() {
  const payload = {
    name: fullName?.value.trim() || "",
    email: email?.value.trim() || "",
    phone: phone?.value.trim() || null,
    location: locationInput?.value.trim() || null,
    birthdate: birthdate?.value || null,
  }

  const requestedPassword = newPassword?.value || ""
  const passwordConfirmation = confirmPassword?.value || ""

  if (requestedPassword || passwordConfirmation || currentPassword?.value) {
    if (requestedPassword !== passwordConfirmation) {
      setMessage("La confirmacion de contrasena no coincide.", true)
      return
    }

    payload.current_password = currentPassword?.value || ""
    payload.new_password = requestedPassword
    payload.new_password_confirmation = passwordConfirmation
  }

  try {
    const submitButton = accountSettingsForm?.querySelector("button[type='submit']")

    if (submitButton) {
      submitButton.disabled = true
      submitButton.textContent = "Guardando..."
    }

    const data = await fetchJson("/me", {
      method: "PUT",
      body: JSON.stringify(payload),
    })

    fillUser(data.user || {})
    clearPasswordFields()
    setMessage(data.message || "Perfil actualizado correctamente.")
  } catch (error) {
    setMessage(error.message, true)
  } finally {
    const submitButton = accountSettingsForm?.querySelector("button[type='submit']")

    if (submitButton) {
      submitButton.disabled = false
      submitButton.textContent = "Guardar cambios"
    }
  }
}

togglePasswordButtons.forEach((button) => {
  button.addEventListener("click", () => {
    const targetId = button.dataset.target
    const targetInput = document.getElementById(targetId)
    const icon = button.querySelector("i")

    if (!targetInput) return

    const isPassword = targetInput.type === "password"
    targetInput.type = isPassword ? "text" : "password"

    if (icon) {
      icon.classList.toggle("bx-hide")
      icon.classList.toggle("bx-show")
    }
  })
})

if (accountSettingsForm) {
  accountSettingsForm.addEventListener("submit", (event) => {
    event.preventDefault()
    saveProfile()
  })
}

loadProfile()
