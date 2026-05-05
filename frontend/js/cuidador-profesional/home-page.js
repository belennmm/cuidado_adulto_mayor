const professionalReminderText = document.getElementById("professionalReminderText")

function formatScheduleReminder(schedule) {
  const days = {
    0: "domingo",
    1: "lunes",
    2: "martes",
    3: "miercoles",
    4: "jueves",
    5: "viernes",
    6: "sabado",
  }

  return `Turno ${days[schedule.day_of_week] || ""} de ${window.ProfessionalCare.formatTime(schedule.start_time)} a ${window.ProfessionalCare.formatTime(schedule.end_time)}`
}

async function loadProfessionalReminder() {
  if (!professionalReminderText || !window.ProfessionalCare) return

  try {
    const data = await window.ProfessionalCare.fetchJson("/professional/overview")
    const nextMedication = data.next_medications?.[0]
    const nextSchedule = data.schedules?.[0]

    if (nextMedication) {
      professionalReminderText.textContent = `${nextMedication.medication_name || "Medicamento"} para ${nextMedication.older_adult_name || "adulto asignado"} a las ${nextMedication.schedule || "hora registrada"}`
      return
    }

    if (nextSchedule) {
      professionalReminderText.textContent = formatScheduleReminder(nextSchedule)
      return
    }

    professionalReminderText.textContent = "No hay recordatorios pendientes"
  } catch (error) {
    professionalReminderText.textContent = error.message
  }
}

document.addEventListener("DOMContentLoaded", loadProfessionalReminder)
