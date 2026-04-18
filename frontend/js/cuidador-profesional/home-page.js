const professionalReminderText = document.getElementById("professionalReminderText")

const professionalHomeData = {
  reminder: "Reunión a las 3 PM"
}

if (professionalReminderText) {
  professionalReminderText.textContent = professionalHomeData.reminder
}