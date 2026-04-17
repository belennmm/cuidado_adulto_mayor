const togglePassword = document.getElementById("togglePassword")
const passwordInput = document.getElementById("password")
const editUserForm = document.getElementById("editUserForm")

const userType = document.getElementById("userType")
const username = document.getElementById("username")
const email = document.getElementById("email")
const locationInput = document.getElementById("location")
const phone = document.getElementById("phone")
const birthdate = document.getElementById("birthdate")
const status = document.getElementById("status")

const usersData = [
  {
    id: 1,
    name: "Ana López",
    role: "Administrador",
    email: "ana.lopez@correo.com",
    phone: "4567-1234",
    status: "Activo",
    location: "Guatemala",
    birthdate: "1995-04-12",
    password: "admin12345"
  },
  {
    id: 2,
    name: "Carlos Méndez",
    role: "Cuidador Profesional",
    email: "carlos.mendez@correo.com",
    phone: "4987-1122",
    status: "Activo",
    location: "Mixco",
    birthdate: "1992-08-21",
    password: "carlos12345"
  },
  {
    id: 3,
    name: "Sofía Ramírez",
    role: "Cuidador Familiar",
    email: "sofia.ramirez@correo.com",
    phone: "5123-8844",
    status: "Pendiente",
    location: "Villa Nueva",
    birthdate: "1998-11-03",
    password: "sofia12345"
  },
  {
    id: 4,
    name: "María Castillo",
    role: "Cuidador Profesional",
    email: "maria.castillo@correo.com",
    phone: "5344-1098",
    status: "Activo",
    location: "Antigua Guatemala",
    birthdate: "1991-02-14",
    password: "maria12345"
  },
  {
    id: 5,
    name: "José Pérez",
    role: "Administrador",
    email: "jose.perez@correo.com",
    phone: "4777-6611",
    status: "Inactivo",
    location: "Guatemala",
    birthdate: "1989-09-27",
    password: "jose12345"
  },
  {
    id: 6,
    name: "Lucía Herrera",
    role: "Cuidador Familiar",
    email: "lucia.herrera@correo.com",
    phone: "5890-7766",
    status: "Activo",
    location: "Santa Catarina Pinula",
    birthdate: "1997-06-18",
    password: "lucia12345"
  }
]

const params = new URLSearchParams(window.location.search)
const userId = Number(params.get("id"))

const selectedUser = usersData.find((user) => user.id === userId)

if (selectedUser) {
  userType.value = selectedUser.role
  username.value = selectedUser.name
  email.value = selectedUser.email
  locationInput.value = selectedUser.location
  phone.value = selectedUser.phone
  birthdate.value = selectedUser.birthdate
  status.value = selectedUser.status
  passwordInput.value = selectedUser.password
}

if (togglePassword && passwordInput) {
  togglePassword.addEventListener("click", () => {
    const isPassword = passwordInput.type === "password"
    passwordInput.type = isPassword ? "text" : "password"

    const icon = togglePassword.querySelector("i")
    if (icon) {
      icon.classList.toggle("bx-hide")
      icon.classList.toggle("bx-show")
    }
  })
}

if (editUserForm) {
  editUserForm.addEventListener("submit", (event) => {
    event.preventDefault()
    alert("Aquí después poder guardar.")
  })
}