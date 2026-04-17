const adminReminderText = document.getElementById("adminReminderText")

const adminHomeData = {
  reminder: "Reunión a las 3 PM"
}

if (adminReminderText) {
  adminReminderText.textContent = adminHomeData.reminder
}