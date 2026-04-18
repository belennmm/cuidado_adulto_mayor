const accountSettingsForm = document.getElementById("accountSettingsForm")

const fullName = document.getElementById("fullName")
const username = document.getElementById("username")
const email = document.getElementById("email")
const phone = document.getElementById("phone")
const locationInput = document.getElementById("location")
const birthdate = document.getElementById("birthdate")
const role = document.getElementById("role")

const profileName = document.getElementById("profileName")
const profileRole = document.getElementById("profileRole")
const profileEmail = document.getElementById("profileEmail")

const togglePasswordButtons = document.querySelectorAll(".toggle-password")

const adminData = {
  fullName: "Ana López",
  username: "ana.lopez",
  email: "ana.lopez@correo.com",
  phone: "4567-1234",
  location: "Guatemala",
  birthdate: "1995-04-12",
  role: "Administrador"
}

if (fullName) fullName.value = adminData.fullName
if (username) username.value = adminData.username
if (email) email.value = adminData.email
if (phone) phone.value = adminData.phone
if (locationInput) locationInput.value = adminData.location
if (birthdate) birthdate.value = adminData.birthdate
if (role) role.value = adminData.role

if (profileName) profileName.textContent = adminData.fullName
if (profileRole) profileRole.textContent = adminData.role
if (profileEmail) profileEmail.textContent = adminData.email

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
    alert("Se han guardado los cambios de tu cuenta.")
  })
}