const newOlderAdultForm = document.getElementById("newOlderAdultForm")
const medicinesList = document.getElementById("medicinesList")
const addMedicineButton = document.getElementById("addMedicineButton")

let medicineCount = 0

function createDayOptions(index) {
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
          <input type="checkbox" name="medicineDays${index}" value="${day.value}" />
          <span>${day.label}</span>
        </label>
      `
    )
    .join("")
}

function addMedicineCard() {
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
        />
      </div>

      <div class="form-group">
        <label for="medicineSchedule${medicineCount}">Horario</label>
        <input
          type="text"
          id="medicineSchedule${medicineCount}"
          name="medicineSchedule${medicineCount}"
          placeholder="Ej. 8:00 AM, 2:00 PM"
        />
      </div>
    </div>

    <div class="days-group">
      <label>Días de administración</label>
      <div class="days-options">
        ${createDayOptions(medicineCount)}
      </div>
    </div>
  `

  const removeButton = card.querySelector(".remove-medicine-button")
  removeButton.addEventListener("click", () => {
    card.remove()
  })

  medicinesList.appendChild(card)
}

if (addMedicineButton) {
  addMedicineButton.addEventListener("click", addMedicineCard)
}

if (newOlderAdultForm) {
  newOlderAdultForm.addEventListener("submit", (event) => {
    event.preventDefault()
    alert("se ha creado el adulto mayor con su información y medicinas asociadas")
  })
}

addMedicineCard()