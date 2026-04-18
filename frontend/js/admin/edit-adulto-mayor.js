const editOlderAdultForm = document.getElementById("editOlderAdultForm")

const fullName = document.getElementById("fullName")
const age = document.getElementById("age")
const birthdate = document.getElementById("birthdate")
const gender = document.getElementById("gender")
const room = document.getElementById("room")
const status = document.getElementById("status")
const caregiverFamily = document.getElementById("caregiverFamily")
const contactName = document.getElementById("contactName")
const contactPhone = document.getElementById("contactPhone")
const allergies = document.getElementById("allergies")
const medicalHistory = document.getElementById("medicalHistory")
const notes = document.getElementById("notes")

const medicinesList = document.getElementById("medicinesList")
const addMedicineButton = document.getElementById("addMedicineButton")

const openDeleteModal = document.getElementById("openDeleteModal")
const closeDeleteModal = document.getElementById("closeDeleteModal")
const confirmDeleteOlderAdult = document.getElementById("confirmDeleteOlderAdult")
const deleteModal = document.getElementById("deleteModal")

const caregiverFamilyOptions = [
  "Laura Rodríguez",
  "José Pérez",
  "Marta Gómez",
  "Ana López"
]

const olderAdultsData = [
  {
    id: 1,
    fullName: "Elena Rodríguez",
    age: 78,
    birthdate: "1946-03-12",
    gender: "Femenino",
    room: "A-101",
    status: "Estable",
    caregiverFamily: "Laura Rodríguez",
    contactName: "Laura Rodríguez",
    contactPhone: "4455-1200",
    allergies: "Penicilina",
    medicalHistory: "Hipertensión controlada. Antecedente de cirugía de cadera.",
    notes: "Requiere supervisión en la mañana",
    medicines: [
      {
        name: "Losartán",
        schedule: "8:00 AM",
        days: ["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"]
      },
      {
        name: "Calcio",
        schedule: "2:00 PM",
        days: ["lunes", "miercoles", "viernes"]
      }
    ]
  },
  {
    id: 2,
    fullName: "Miguel Herrera",
    age: 81,
    birthdate: "1943-07-01",
    gender: "Masculino",
    room: "B-204",
    status: "Atención",
    caregiverFamily: "José Pérez",
    contactName: "Luis Herrera",
    contactPhone: "5544-8801",
    allergies: "Ninguna",
    medicalHistory: "Diabetes tipo 2. Control frecuente de presión.",
    notes: "Control frecuente de presión",
    medicines: [
      {
        name: "Metformina",
        schedule: "7:00 AM, 7:00 PM",
        days: ["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"]
      }
    ]
  },
  {
    id: 3,
    fullName: "Rosa Pérez",
    age: 74,
    birthdate: "1950-10-18",
    gender: "Femenino",
    room: "A-115",
    status: "Estable",
    caregiverFamily: "Marta Gómez",
    contactName: "Marta Pérez",
    contactPhone: "5123-9911",
    allergies: "Mariscos",
    medicalHistory: "Antecedente de artritis.",
    notes: "Dieta suave",
    medicines: [
      {
        name: "Ibuprofeno",
        schedule: "9:00 AM",
        days: ["martes", "jueves", "sabado"]
      }
    ]
  },
  {
    id: 4,
    fullName: "Jorge Ramírez",
    age: 85,
    birthdate: "1939-01-22",
    gender: "Masculino",
    room: "C-302",
    status: "Crítico",
    caregiverFamily: "Ana López",
    contactName: "Andrea Ramírez",
    contactPhone: "4778-3301",
    allergies: "Polvo",
    medicalHistory: "Insuficiencia cardíaca. Monitoreo constante.",
    notes: "Monitoreo constante",
    medicines: [
      {
        name: "Furosemida",
        schedule: "8:00 AM",
        days: ["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"]
      },
      {
        name: "Aspirina",
        schedule: "1:00 PM",
        days: ["lunes", "martes", "miercoles", "jueves", "viernes"]
      }
    ]
  },
  {
    id: 5,
    fullName: "Marta López",
    age: 79,
    birthdate: "1945-06-30",
    gender: "Femenino",
    room: "B-210",
    status: "Atención",
    caregiverFamily: "Laura Rodríguez",
    contactName: "José López",
    contactPhone: "5667-1212",
    allergies: "Lácteos",
    medicalHistory: "Problemas de movilidad y osteoporosis.",
    notes: "Ayuda para movilización",
    medicines: [
      {
        name: "Vitamina D",
        schedule: "10:00 AM",
        days: ["lunes", "miercoles", "viernes"]
      }
    ]
  },
  {
    id: 6,
    fullName: "Ricardo Gómez",
    age: 83,
    birthdate: "1941-09-05",
    gender: "Masculino",
    room: "A-122",
    status: "Estable",
    caregiverFamily: "José Pérez",
    contactName: "Claudia Gómez",
    contactPhone: "5990-8800",
    allergies: "Ninguna",
    medicalHistory: "Sin complicaciones recientes.",
    notes: "Rutina estable",
    medicines: [
      {
        name: "Omeprazol",
        schedule: "8:00 AM",
        days: ["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"]
      }
    ]
  }
]

let medicineCount = 0

function createDayOptions(index, selectedDays = []) {
  const days = [
    { value: "lunes", label: "Lunes" },
    { value: "martes", label: "Martes" },
    { value: "miercoles", label: "Miércoles" },
    { value: "jueves", label: "Jueves" },
    { value: "viernes", label: "Viernes" },
    { value: "sabado", label: "Sábado" },
    { value: "domingo", label: "Domingo" }
  ]

  return days
    .map(
      (day) => `
        <label class="day-option">
          <input type="checkbox" name="medicineDays${index}" value="${day.value}" ${selectedDays.includes(day.value) ? "checked" : ""} />
          <span>${day.label}</span>
        </label>
      `
    )
    .join("")
}

function addMedicineCard(medicine = null) {
  medicineCount += 1

  const card = document.createElement("div")
  card.className = "medicine-card"
  card.dataset.index = medicineCount

  card.innerHTML = `
    <div class="medicine-card-header">
      <h3 class="medicine-card-title">Medicina ${medicineCount}</h3>
      <button type="button" class="remove-medicine-button">Eliminar</button>
    </div>

    <div class="medicine-grid">
      <div class="form-group">
        <label for="medicineName${medicineCount}">Nombre de medicina</label>
        <input
          type="text"
          id="medicineName${medicineCount}"
          name="medicineName${medicineCount}"
          placeholder="Ingrese nombre de medicina"
          value="${medicine?.name || ""}"
        />
      </div>

      <div class="form-group">
        <label for="medicineSchedule${medicineCount}">Horario</label>
        <input
          type="text"
          id="medicineSchedule${medicineCount}"
          name="medicineSchedule${medicineCount}"
          placeholder="Ej. 8:00 AM, 2:00 PM"
          value="${medicine?.schedule || ""}"
        />
      </div>
    </div>

    <div class="days-group">
      <label>Días de administración</label>
      <div class="days-options">
        ${createDayOptions(medicineCount, medicine?.days || [])}
      </div>
    </div>
  `

  const removeButton = card.querySelector(".remove-medicine-button")
  removeButton.addEventListener("click", () => {
    card.remove()
  })

  medicinesList.appendChild(card)
}

const params = new URLSearchParams(window.location.search)
const olderAdultId = Number(params.get("id"))

const selectedOlderAdult = olderAdultsData.find((olderAdult) => olderAdult.id === olderAdultId)

if (selectedOlderAdult) {
  fullName.value = selectedOlderAdult.fullName || ""
  age.value = selectedOlderAdult.age || ""
  birthdate.value = selectedOlderAdult.birthdate || ""
  gender.value = selectedOlderAdult.gender || ""
  room.value = selectedOlderAdult.room || ""
  status.value = selectedOlderAdult.status || ""
  caregiverFamily.value = selectedOlderAdult.caregiverFamily || ""
  contactName.value = selectedOlderAdult.contactName || ""
  contactPhone.value = selectedOlderAdult.contactPhone || ""
  allergies.value = selectedOlderAdult.allergies || ""
  medicalHistory.value = selectedOlderAdult.medicalHistory || ""
  notes.value = selectedOlderAdult.notes || ""

  medicinesList.innerHTML = ""
  selectedOlderAdult.medicines.forEach((medicine) => addMedicineCard(medicine))
} else {
  addMedicineCard()
}

if (addMedicineButton) {
  addMedicineButton.addEventListener("click", () => addMedicineCard())
}

if (editOlderAdultForm) {
  editOlderAdultForm.addEventListener("submit", (event) => {
    event.preventDefault()
    alert("Se guardaron los cambios del adulto mayor.")
  })
}

if (openDeleteModal && deleteModal) {
  openDeleteModal.addEventListener("click", () => {
    deleteModal.classList.add("active")
  })
}

if (closeDeleteModal && deleteModal) {
  closeDeleteModal.addEventListener("click", () => {
    deleteModal.classList.remove("active")
  })
}

if (deleteModal) {
  deleteModal.addEventListener("click", (event) => {
    if (event.target === deleteModal) {
      deleteModal.classList.remove("active")
    }
  })
}

if (confirmDeleteOlderAdult) {
  confirmDeleteOlderAdult.addEventListener("click", () => {
    alert("Adulto mayor eliminado")
    window.location.href = "./adultos-mayores.html"
  })
}